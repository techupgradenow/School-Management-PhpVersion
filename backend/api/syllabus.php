<?php
/**
 * Syllabus API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for syllabus, chapters, topics, and progress
 * Protected by Permission Guard - enforces strict RBAC
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

// Require authentication and module access for ALL requests
requireAuth();
requireModuleAccess('syllabus');

// Route requests with permission checks
switch ($method) {
    case 'GET':
        requirePermission('syllabus', 'view');
        handleGet($db, $_GET);
        break;

    case 'POST':
        requirePermission('syllabus', 'create');
        handlePost($db, $data);
        break;

    case 'PUT':
        requirePermission('syllabus', 'edit');
        handlePut($db, $data);
        break;

    case 'DELETE':
        requirePermission('syllabus', 'delete');
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
                getSyllabusList($db, $params);
                break;

            case 'single':
                getSingleSyllabus($db, $params);
                break;

            case 'chapters':
                getChapters($db, $params);
                break;

            case 'topics':
                getTopics($db, $params);
                break;

            case 'stats':
                getSyllabusStats($db, $params);
                break;

            case 'progress':
                getProgress($db, $params);
                break;

            case 'files':
                getSyllabusFiles($db, $params);
                break;

            case 'logs':
                getSyllabusLogs($db, $params);
                break;

            case 'custom_subjects':
                getCustomSubjects($db, $params);
                break;

            // Dependent dropdown endpoints
            case 'classes':
                getClassesList($db, $params);
                break;

            case 'sections_by_class':
                getSectionsByClass($db, $params);
                break;

            case 'subjects_by_class':
                getSubjectsByClass($db, $params);
                break;

            case 'teachers_by_subject':
                getTeachersBySubject($db, $params);
                break;

            case 'academic_years':
                getAcademicYears($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get custom subjects list
 */
function getCustomSubjects($db, $params) {
    try {
        $stmt = $db->query("SELECT subject_name FROM custom_subjects ORDER BY subject_name ASC");
        $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
        sendResponse(true, 'Custom subjects fetched successfully', $subjects);
    } catch (Exception $e) {
        sendResponse(true, 'No custom subjects found', []);
    }
}

// ============================================
// DEPENDENT DROPDOWN FUNCTIONS
// ============================================

/**
 * Get all classes list
 */
function getClassesList($db, $params) {
    try {
        $stmt = $db->query("
            SELECT DISTINCT id, class_name as name, capacity as description
            FROM classes
            ORDER BY
                CASE
                    WHEN class_name REGEXP '^[0-9]' THEN CAST(REGEXP_SUBSTR(class_name, '[0-9]+') AS UNSIGNED)
                    WHEN class_name REGEXP 'Class [0-9]' THEN CAST(REGEXP_SUBSTR(class_name, '[0-9]+') AS UNSIGNED)
                    ELSE 999
                END,
                class_name ASC
        ");
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(true, 'Classes fetched successfully', ['classes' => $classes]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching classes', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get sections by class (dependent dropdown)
 */
function getSectionsByClass($db, $params) {
    $classId = $params['class_id'] ?? null;
    $className = $params['class_name'] ?? null;

    try {
        if ($classId) {
            $stmt = $db->prepare("
                SELECT s.id, s.section_name as name, s.capacity
                FROM sections s
                WHERE s.class_id = :class_id
                ORDER BY s.section_name ASC
            ");
            $stmt->execute([':class_id' => $classId]);
        } elseif ($className) {
            $stmt = $db->prepare("
                SELECT s.id, s.section_name as name, s.capacity
                FROM sections s
                JOIN classes c ON s.class_id = c.id
                WHERE c.class_name = :class_name
                ORDER BY s.section_name ASC
            ");
            $stmt->execute([':class_name' => $className]);
        } else {
            $stmt = $db->query("
                SELECT s.id, s.section_name as name, s.capacity, c.class_name
                FROM sections s
                LEFT JOIN classes c ON s.class_id = c.id
                ORDER BY s.section_name ASC
            ");
        }

        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sections)) {
            $sections = [
                ['id' => 'A', 'name' => 'A', 'capacity' => null],
                ['id' => 'B', 'name' => 'B', 'capacity' => null],
                ['id' => 'C', 'name' => 'C', 'capacity' => null],
                ['id' => 'D', 'name' => 'D', 'capacity' => null]
            ];
        }

        sendResponse(true, 'Sections fetched successfully', ['sections' => $sections]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching sections', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get subjects by class (dependent dropdown)
 */
function getSubjectsByClass($db, $params) {
    $classId = $params['class_id'] ?? null;
    $className = $params['class'] ?? $params['class_name'] ?? null;

    try {
        $subjects = [];

        if ($classId || $className) {
            if ($classId) {
                // Try to get subjects by class_id (if subjects table has class_id column)
                // First check if subjects table has class_id
                try {
                    $stmt = $db->prepare("
                        SELECT DISTINCT
                            sub.id,
                            sub.name as subject_name,
                            sub.code as subject_code,
                            sub.teacher_id,
                            t.name as teacher_name
                        FROM subjects sub
                        LEFT JOIN teachers t ON sub.teacher_id = t.id
                        LEFT JOIN classes c ON sub.class = c.class_name
                        WHERE c.id = :class_id
                        ORDER BY sub.name ASC
                    ");
                    $stmt->execute([':class_id' => $classId]);
                    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // Fallback - subjects.class is a text field
                    $subjects = [];
                }
            }

            if (empty($subjects) && $className) {
                // subjects.class is a text field with class name
                $stmt = $db->prepare("
                    SELECT DISTINCT
                        sub.id,
                        sub.name as subject_name,
                        sub.code as subject_code,
                        sub.teacher_id,
                        t.name as teacher_name
                    FROM subjects sub
                    LEFT JOIN teachers t ON sub.teacher_id = t.id
                    WHERE sub.class = :class_name OR sub.class LIKE :class_like
                    ORDER BY sub.name ASC
                ");
                $stmt->execute([
                    ':class_name' => $className,
                    ':class_like' => '%' . $className . '%'
                ]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        // Also get custom subjects
        try {
            $customStmt = $db->query("SELECT subject_name FROM custom_subjects ORDER BY subject_name ASC");
            $customSubjects = $customStmt->fetchAll(PDO::FETCH_COLUMN);

            $subjectNames = array_column($subjects, 'subject_name');
            foreach ($customSubjects as $custom) {
                if (!in_array($custom, $subjectNames)) {
                    $subjects[] = [
                        'id' => null,
                        'subject_name' => $custom,
                        'subject_code' => null,
                        'teacher_id' => null,
                        'teacher_name' => null,
                        'is_custom' => true
                    ];
                }
            }
        } catch (Exception $e) {
            // custom_subjects table might not exist
        }

        if (empty($subjects)) {
            $defaultSubjects = [
                'Mathematics', 'Science', 'English', 'Hindi', 'Social Studies',
                'Physics', 'Chemistry', 'Biology', 'Computer Science', 'History',
                'Geography', 'Economics', 'Political Science', 'Sanskrit'
            ];
            foreach ($defaultSubjects as $subj) {
                $subjects[] = [
                    'id' => null,
                    'subject_name' => $subj,
                    'subject_code' => null,
                    'teacher_id' => null,
                    'teacher_name' => null,
                    'is_default' => true
                ];
            }
        }

        sendResponse(true, 'Subjects fetched successfully', [
            'subjects' => $subjects,
            'class' => $className ?? $classId
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching subjects', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get teachers by subject/class (dependent dropdown)
 */
function getTeachersBySubject($db, $params) {
    $subjectId = $params['subject_id'] ?? null;
    $subjectName = $params['subject_name'] ?? $params['subject'] ?? null;
    $classId = $params['class_id'] ?? null;
    $className = $params['class'] ?? $params['class_name'] ?? null;

    try {
        $teachers = [];

        if ($subjectId) {
            $stmt = $db->prepare("
                SELECT t.id, t.name, t.employee_id, t.email, t.department
                FROM teachers t
                JOIN subjects sub ON t.id = sub.teacher_id
                WHERE sub.id = :subject_id
            ");
            $stmt->execute([':subject_id' => $subjectId]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($subjectName) {
            $stmt = $db->prepare("
                SELECT DISTINCT t.id, t.name, t.employee_id, t.email, t.department
                FROM teachers t
                JOIN subjects sub ON t.id = sub.teacher_id
                WHERE LOWER(sub.name) LIKE LOWER(:subject_name)
            ");
            $stmt->execute([':subject_name' => '%' . $subjectName . '%']);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($teachers)) {
            $stmt = $db->query("
                SELECT id, name, employee_id, email, department
                FROM teachers
                WHERE status = 'Active' OR status IS NULL
                ORDER BY name ASC
                LIMIT 50
            ");
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendResponse(true, 'Teachers fetched successfully', [
            'teachers' => $teachers,
            'subject' => $subjectName ?? $subjectId,
            'class' => $className ?? $classId
        ]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching teachers', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get academic years list
 */
function getAcademicYears($db, $params) {
    try {
        $stmt = $db->query("
            SELECT DISTINCT academic_year
            FROM syllabus
            WHERE academic_year IS NOT NULL AND academic_year != ''
            ORDER BY academic_year DESC
        ");
        $existingYears = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $currentYear = (int)date('Y');
        $defaultYears = [];
        for ($i = -1; $i <= 2; $i++) {
            $startYear = $currentYear + $i;
            $endYear = $startYear + 1;
            $defaultYears[] = "$startYear-$endYear";
        }

        $allYears = array_unique(array_merge($existingYears, $defaultYears));
        rsort($allYears);

        sendResponse(true, 'Academic years fetched successfully', ['years' => array_values($allYears)]);
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching academic years', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle POST requests
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'create_syllabus';

        switch ($action) {
            case 'create_syllabus':
                createSyllabus($db, $data);
                break;

            case 'bulk_create_syllabus':
                bulkCreateSyllabus($db, $data);
                break;

            case 'add_custom_subject':
                addCustomSubject($db, $data);
                break;

            case 'add_chapter':
                addChapter($db, $data);
                break;

            case 'add_topic':
                addTopic($db, $data);
                break;

            case 'mark_progress':
                markProgress($db, $data);
                break;

            case 'upload_file':
                uploadSyllabusFile($db, $data);
                break;

            case 'publish':
                publishSyllabus($db, $data);
                break;

            case 'archive':
                archiveSyllabus($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Publish syllabus (change status to Active)
 */
function publishSyllabus($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $stmt = $db->prepare("UPDATE syllabus SET status = 'Active', updated_by = :updated_by WHERE id = :id");
    $result = $stmt->execute([
        ':id' => $data['id'],
        ':updated_by' => getCurrentUserId()
    ]);

    if ($result) {
        logSyllabusActivity($db, $data['id'], 'PUBLISH', 'Published syllabus');
        sendResponse(true, 'Syllabus published successfully');
    } else {
        sendResponse(false, 'Failed to publish syllabus');
    }
}

/**
 * Archive syllabus
 */
function archiveSyllabus($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $stmt = $db->prepare("UPDATE syllabus SET status = 'Archived', updated_by = :updated_by WHERE id = :id");
    $result = $stmt->execute([
        ':id' => $data['id'],
        ':updated_by' => getCurrentUserId()
    ]);

    if ($result) {
        logSyllabusActivity($db, $data['id'], 'ARCHIVE', 'Archived syllabus');
        sendResponse(true, 'Syllabus archived successfully');
    } else {
        sendResponse(false, 'Failed to archive syllabus');
    }
}

/**
 * Handle PUT requests
 */
function handlePut($db, $data) {
    try {
        $type = $data['type'] ?? 'syllabus';

        switch ($type) {
            case 'syllabus':
                updateSyllabus($db, $data);
                break;

            case 'chapter':
                updateChapter($db, $data);
                break;

            case 'topic':
                updateTopic($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid update type');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($db, $params) {
    try {
        $type = $params['type'] ?? 'syllabus';
        $id = $params['id'] ?? '';

        if (empty($id)) {
            sendResponse(false, 'ID is required');
        }

        switch ($type) {
            case 'syllabus':
                deleteSyllabus($db, $id);
                break;

            case 'chapter':
                deleteChapter($db, $id);
                break;

            case 'topic':
                deleteTopic($db, $id);
                break;

            default:
                sendResponse(false, 'Invalid delete type');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

// ============================================
// GET FUNCTIONS
// ============================================

/**
 * Get syllabus list
 */
function getSyllabusList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $class = $params['class'] ?? '';
    $subject = $params['subject'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';
    $academic_year = $params['academic_year'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "s.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($subject)) {
        $where[] = "s.subject = :subject";
        $bindings[':subject'] = $subject;
    }

    if (!empty($status)) {
        $where[] = "s.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($academic_year)) {
        $where[] = "s.academic_year = :academic_year";
        $bindings[':academic_year'] = $academic_year;
    }

    if (!empty($search)) {
        $where[] = "(s.title LIKE :search OR s.subject LIKE :search OR s.id LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM syllabus s $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records with chapter count
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT
            s.*,
            (SELECT COUNT(*) FROM syllabus_chapters WHERE syllabus_id = s.id) as chapter_count,
            (SELECT COUNT(*) FROM syllabus_topics t
             JOIN syllabus_chapters c ON t.chapter_id = c.id
             WHERE c.syllabus_id = s.id) as topic_count
        FROM syllabus s
        $whereClause
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $syllabi = $stmt->fetchAll();

    $response = [
        'syllabi' => $syllabi,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Syllabus list fetched successfully', $response);
}

/**
 * Get single syllabus with chapters and topics
 */
function getSingleSyllabus($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $syllabusId = $params['id'];

    // Get syllabus
    $stmt = $db->prepare("SELECT * FROM syllabus WHERE id = :id");
    $stmt->execute([':id' => $syllabusId]);
    $syllabus = $stmt->fetch();

    if (!$syllabus) {
        sendResponse(false, 'Syllabus not found');
    }

    // Get chapters with topics
    $chaptersStmt = $db->prepare("
        SELECT * FROM syllabus_chapters
        WHERE syllabus_id = :syllabus_id
        ORDER BY chapter_no ASC
    ");
    $chaptersStmt->execute([':syllabus_id' => $syllabusId]);
    $chapters = $chaptersStmt->fetchAll();

    // Get topics for each chapter
    foreach ($chapters as &$chapter) {
        $topicsStmt = $db->prepare("
            SELECT * FROM syllabus_topics
            WHERE chapter_id = :chapter_id
            ORDER BY topic_no ASC
        ");
        $topicsStmt->execute([':chapter_id' => $chapter['id']]);
        $chapter['topics'] = $topicsStmt->fetchAll();
    }

    $syllabus['chapters'] = $chapters;

    sendResponse(true, 'Syllabus fetched successfully', $syllabus);
}

/**
 * Get chapters for a syllabus
 */
function getChapters($db, $params) {
    if (empty($params['syllabus_id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $stmt = $db->prepare("
        SELECT
            c.*,
            (SELECT COUNT(*) FROM syllabus_topics WHERE chapter_id = c.id) as topic_count
        FROM syllabus_chapters c
        WHERE c.syllabus_id = :syllabus_id
        ORDER BY c.chapter_no ASC
    ");
    $stmt->execute([':syllabus_id' => $params['syllabus_id']]);
    $chapters = $stmt->fetchAll();

    sendResponse(true, 'Chapters fetched successfully', ['chapters' => $chapters]);
}

/**
 * Get topics for a chapter
 */
function getTopics($db, $params) {
    if (empty($params['chapter_id'])) {
        sendResponse(false, 'Chapter ID is required');
    }

    $stmt = $db->prepare("
        SELECT * FROM syllabus_topics
        WHERE chapter_id = :chapter_id
        ORDER BY topic_no ASC
    ");
    $stmt->execute([':chapter_id' => $params['chapter_id']]);
    $topics = $stmt->fetchAll();

    sendResponse(true, 'Topics fetched successfully', ['topics' => $topics]);
}

/**
 * Get syllabus statistics
 */
function getSyllabusStats($db, $params) {
    // Total syllabi
    $totalStmt = $db->query("SELECT COUNT(*) as total FROM syllabus");
    $totalSyllabi = $totalStmt->fetch()['total'];

    // Active syllabi
    $activeStmt = $db->query("SELECT COUNT(*) as total FROM syllabus WHERE status = 'Active'");
    $activeSyllabi = $activeStmt->fetch()['total'];

    // Total chapters
    $chaptersStmt = $db->query("SELECT COUNT(*) as total FROM syllabus_chapters");
    $totalChapters = $chaptersStmt->fetch()['total'];

    // Total topics
    $topicsStmt = $db->query("SELECT COUNT(*) as total FROM syllabus_topics");
    $totalTopics = $topicsStmt->fetch()['total'];

    // Completed topics (from progress)
    $completedStmt = $db->query("SELECT COUNT(*) as total FROM syllabus_progress WHERE is_completed = 1");
    $completedTopics = $completedStmt->fetch()['total'];

    // Calculate completion rate
    $completionRate = $totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100, 1) : 0;

    // Active classes (distinct classes with active syllabi)
    $classesStmt = $db->query("SELECT COUNT(DISTINCT class) as total FROM syllabus WHERE status = 'Active'");
    $activeClasses = $classesStmt->fetch()['total'];

    $stats = [
        'total_syllabi' => (int)$totalSyllabi,
        'active_syllabi' => (int)$activeSyllabi,
        'total_chapters' => (int)$totalChapters,
        'total_topics' => (int)$totalTopics,
        'completed_topics' => (int)$completedTopics,
        'completion_rate' => $completionRate,
        'active_classes' => (int)$activeClasses
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get progress for a syllabus/class/section
 */
function getProgress($db, $params) {
    $syllabusId = $params['syllabus_id'] ?? '';
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    if (empty($syllabusId)) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $where = ["c.syllabus_id = :syllabus_id"];
    $bindings = [':syllabus_id' => $syllabusId];

    if (!empty($class)) {
        $bindings[':class'] = $class;
    }
    if (!empty($section)) {
        $bindings[':section'] = $section;
    }

    // Get all topics with their progress
    $query = "
        SELECT
            t.id as topic_id,
            t.chapter_id,
            t.title as topic_title,
            c.id as chapter_id,
            c.title as chapter_title,
            c.chapter_no,
            p.is_completed,
            p.completed_at,
            p.completed_by
        FROM syllabus_topics t
        JOIN syllabus_chapters c ON t.chapter_id = c.id
        LEFT JOIN syllabus_progress p ON t.id = p.topic_id
            " . (!empty($class) ? "AND p.class = :class" : "") . "
            " . (!empty($section) ? "AND p.section = :section" : "") . "
        WHERE c.syllabus_id = :syllabus_id
        ORDER BY c.chapter_no, t.topic_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $progress = $stmt->fetchAll();

    // Calculate summary
    $totalTopics = count($progress);
    $completedTopics = count(array_filter($progress, function($p) { return $p['is_completed']; }));
    $completionRate = $totalTopics > 0 ? round(($completedTopics / $totalTopics) * 100, 1) : 0;

    $response = [
        'progress' => $progress,
        'summary' => [
            'total_topics' => $totalTopics,
            'completed_topics' => $completedTopics,
            'completion_rate' => $completionRate
        ]
    ];

    sendResponse(true, 'Progress fetched successfully', $response);
}

// ============================================
// POST FUNCTIONS
// ============================================

/**
 * Create new syllabus
 */
function createSyllabus($db, $data) {
    $required = ['class', 'subject', 'academic_year', 'title'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Generate ID
    $id = generateSyllabusId($db);

    // Check for duplicate (only non-deleted)
    $checkStmt = $db->prepare("
        SELECT id FROM syllabus
        WHERE class = :class AND subject = :subject AND academic_year = :academic_year
        AND (is_deleted = 0 OR is_deleted IS NULL)
    ");
    $checkStmt->execute([
        ':class' => $data['class'],
        ':subject' => $data['subject'],
        ':academic_year' => $data['academic_year']
    ]);

    if ($checkStmt->fetch()) {
        sendResponse(false, 'Syllabus already exists for this class, subject, and academic year');
    }

    $stmt = $db->prepare("
        INSERT INTO syllabus (id, class, section, subject, academic_year, board_type, medium,
            total_marks, exam_pattern, title, description, status, version, created_by)
        VALUES (:id, :class, :section, :subject, :academic_year, :board_type, :medium,
            :total_marks, :exam_pattern, :title, :description, :status, 1, :created_by)
    ");

    $result = $stmt->execute([
        ':id' => $id,
        ':class' => sanitizeInput($data['class']),
        ':section' => sanitizeInput($data['section'] ?? ''),
        ':subject' => sanitizeInput($data['subject']),
        ':academic_year' => sanitizeInput($data['academic_year']),
        ':board_type' => $data['board_type'] ?? 'CBSE',
        ':medium' => $data['medium'] ?? 'English',
        ':total_marks' => !empty($data['total_marks']) ? (int)$data['total_marks'] : null,
        ':exam_pattern' => sanitizeInput($data['exam_pattern'] ?? ''),
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description'] ?? ''),
        ':status' => $data['status'] ?? 'Draft',
        ':created_by' => getCurrentUserId()
    ]);

    if ($result) {
        // Log activity
        logSyllabusActivity($db, $id, 'CREATE', 'Created syllabus: ' . $data['title']);

        logActivity($db, getCurrentUserId(), 'Created syllabus: ' . $data['title'], 'Syllabus', [
            'syllabus_id' => $id,
            'class' => $data['class'],
            'subject' => $data['subject']
        ]);
        sendResponse(true, 'Syllabus created successfully', ['id' => $id]);
    } else {
        sendResponse(false, 'Failed to create syllabus');
    }
}

/**
 * Bulk create syllabus for multiple subjects with user-defined codes
 */
function bulkCreateSyllabus($db, $data) {
    $required = ['class', 'academic_year', 'subjects'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    if (!is_array($data['subjects']) || empty($data['subjects'])) {
        sendResponse(false, 'At least one subject is required');
    }

    $class = sanitizeInput($data['class']);
    $section = sanitizeInput($data['section'] ?? '');
    $academicYear = sanitizeInput($data['academic_year']);
    $boardType = $data['board_type'] ?? 'CBSE';
    $medium = $data['medium'] ?? 'English';
    $createdBy = getCurrentUserId();

    $created = [];
    $failed = [];

    // Start transaction
    $db->beginTransaction();

    try {
        $checkStmt = $db->prepare("
            SELECT id FROM syllabus
            WHERE class = :class AND subject = :subject AND academic_year = :academic_year
            AND (section = :section OR (section IS NULL AND :section2 = ''))
            AND (is_deleted = 0 OR is_deleted IS NULL)
        ");

        $insertStmt = $db->prepare("
            INSERT INTO syllabus (id, syllabus_code, class, section, subject, academic_year, board_type, medium,
                total_marks, title, description, status, version, created_by)
            VALUES (:id, :syllabus_code, :class, :section, :subject, :academic_year, :board_type, :medium,
                :total_marks, :title, :description, :status, 1, :created_by)
        ");

        foreach ($data['subjects'] as $subjectData) {
            $subject = sanitizeInput($subjectData['subject'] ?? $subjectData);
            $totalMarks = isset($subjectData['total_marks']) ? (int)$subjectData['total_marks'] : null;
            $status = $subjectData['status'] ?? 'Draft';
            $title = isset($subjectData['title']) ? sanitizeInput($subjectData['title'])
                : "{$class} {$subject} Syllabus {$academicYear}";

            // Use user-provided syllabus code or generate one
            $syllabusCode = !empty($subjectData['syllabus_code'])
                ? sanitizeInput($subjectData['syllabus_code'])
                : generateSyllabusCode($class, $section, $subject, $academicYear);

            // Check for duplicate
            $checkStmt->execute([
                ':class' => $class,
                ':subject' => $subject,
                ':academic_year' => $academicYear,
                ':section' => $section,
                ':section2' => $section
            ]);

            if ($checkStmt->fetch()) {
                $failed[] = [
                    'subject' => $subject,
                    'reason' => 'Already exists'
                ];
                continue;
            }

            // Generate ID
            $id = generateSyllabusId($db);

            // Insert
            $result = $insertStmt->execute([
                ':id' => $id,
                ':syllabus_code' => $syllabusCode,
                ':class' => $class,
                ':section' => $section,
                ':subject' => $subject,
                ':academic_year' => $academicYear,
                ':board_type' => $boardType,
                ':medium' => $medium,
                ':total_marks' => $totalMarks,
                ':title' => $title,
                ':description' => '',
                ':status' => $status,
                ':created_by' => $createdBy
            ]);

            if ($result) {
                $created[] = [
                    'id' => $id,
                    'syllabus_code' => $syllabusCode,
                    'subject' => $subject,
                    'title' => $title
                ];

                // Log activity
                logSyllabusActivity($db, $id, 'CREATE', "Bulk created syllabus: {$title}");
            } else {
                $failed[] = [
                    'subject' => $subject,
                    'reason' => 'Insert failed'
                ];
            }
        }

        // Commit transaction
        $db->commit();

        $message = count($created) . ' syllabus created successfully for ' . $class;
        if (!empty($section)) {
            $message .= ' - Section ' . $section;
        }

        if (!empty($failed)) {
            $message .= '. ' . count($failed) . ' failed.';
        }

        logActivity($db, $createdBy, $message, 'Syllabus', [
            'class' => $class,
            'section' => $section,
            'count' => count($created)
        ]);

        sendResponse(true, $message, [
            'created' => $created,
            'failed' => $failed,
            'total_created' => count($created),
            'total_failed' => count($failed)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Bulk creation failed: ' . $e->getMessage());
    }
}

/**
 * Add a custom subject to the database for future use
 */
function addCustomSubject($db, $data) {
    if (empty($data['subject_name'])) {
        sendResponse(false, 'Subject name is required');
    }

    $subjectName = sanitizeInput($data['subject_name']);

    // Check if already exists
    $checkStmt = $db->prepare("SELECT id FROM custom_subjects WHERE LOWER(subject_name) = LOWER(:name)");
    $checkStmt->execute([':name' => $subjectName]);

    if ($checkStmt->fetch()) {
        sendResponse(false, 'This subject already exists');
    }

    // Insert new custom subject
    $stmt = $db->prepare("
        INSERT INTO custom_subjects (subject_name, created_by)
        VALUES (:subject_name, :created_by)
    ");

    $result = $stmt->execute([
        ':subject_name' => $subjectName,
        ':created_by' => getCurrentUserId()
    ]);

    if ($result) {
        logActivity($db, getCurrentUserId(), "Added custom subject: {$subjectName}", 'Syllabus', [
            'subject_name' => $subjectName
        ]);
        sendResponse(true, 'Custom subject added successfully', [
            'id' => $db->lastInsertId(),
            'subject_name' => $subjectName
        ]);
    } else {
        sendResponse(false, 'Failed to add custom subject');
    }
}

/**
 * Generate unique syllabus code
 * Format: SYL-{CLASS}-{SECTION}-{SUBJECTCODE}-{YEAR}
 */
function generateSyllabusCode($class, $section, $subject, $academicYear) {
    // Extract class number (2 digits, padded)
    $classNum = preg_replace('/[^0-9]/', '', $class);
    $classNum = str_pad($classNum, 2, '0', STR_PAD_LEFT);

    // Section (1 char, default 'A')
    $sectionCode = strtoupper(substr(trim($section) ?: 'A', 0, 1));

    // Subject code mapping (3-4 chars)
    $subjectCodes = [
        'mathematics' => 'MATH', 'math' => 'MATH',
        'science' => 'SCI', 'english' => 'ENG', 'hindi' => 'HIN',
        'social' => 'SST', 'social studies' => 'SST',
        'physics' => 'PHY', 'chemistry' => 'CHE', 'biology' => 'BIO',
        'computer' => 'CS', 'computer science' => 'CS',
        'history' => 'HIS', 'geography' => 'GEO', 'economics' => 'ECO',
        'political' => 'POL', 'political science' => 'POL',
        'sanskrit' => 'SKT', 'french' => 'FRE', 'german' => 'GER',
        'accountancy' => 'ACC', 'accounts' => 'ACC',
        'business' => 'BUS', 'business studies' => 'BUS',
        'physical education' => 'PE', 'art' => 'ART', 'music' => 'MUS',
        'environmental' => 'EVS', 'evs' => 'EVS'
    ];

    $subjectLower = strtolower(trim($subject));
    $subjectCode = 'SUB';

    foreach ($subjectCodes as $key => $code) {
        if (strpos($subjectLower, $key) !== false) {
            $subjectCode = $code;
            break;
        }
    }

    // If no match found, use first 4 characters of subject
    if ($subjectCode === 'SUB') {
        $subjectCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $subject), 0, 4));
        if (empty($subjectCode)) {
            $subjectCode = 'SUB';
        }
    }

    // Year code (last 2 digits of each year)
    $yearParts = explode('-', $academicYear);
    $startYear = substr(trim($yearParts[0]), -2);
    $endYear = isset($yearParts[1]) ? substr(trim($yearParts[1]), -2) : $startYear;
    $yearCode = $startYear . $endYear;

    return "SYL-{$classNum}-{$sectionCode}-{$subjectCode}-{$yearCode}";
}

/**
 * Add chapter to syllabus
 */
function addChapter($db, $data) {
    $required = ['syllabus_id', 'chapter_no', 'title'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Verify syllabus exists
    $checkStmt = $db->prepare("SELECT id FROM syllabus WHERE id = :id");
    $checkStmt->execute([':id' => $data['syllabus_id']]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Syllabus not found');
    }

    // Generate chapter ID
    $id = generateChapterId($db);

    $stmt = $db->prepare("
        INSERT INTO syllabus_chapters (id, syllabus_id, chapter_no, title, description, estimated_hours)
        VALUES (:id, :syllabus_id, :chapter_no, :title, :description, :estimated_hours)
    ");

    $result = $stmt->execute([
        ':id' => $id,
        ':syllabus_id' => $data['syllabus_id'],
        ':chapter_no' => (int)$data['chapter_no'],
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description'] ?? ''),
        ':estimated_hours' => (float)($data['estimated_hours'] ?? 0)
    ]);

    if ($result) {
        logActivity($db, getCurrentUserId(), 'Added chapter: ' . $data['title'], 'Syllabus', [
            'chapter_id' => $id,
            'syllabus_id' => $data['syllabus_id']
        ]);
        sendResponse(true, 'Chapter added successfully', ['id' => $id]);
    } else {
        sendResponse(false, 'Failed to add chapter');
    }
}

/**
 * Add topic to chapter
 */
function addTopic($db, $data) {
    $required = ['chapter_id', 'topic_no', 'title'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Verify chapter exists
    $checkStmt = $db->prepare("SELECT id FROM syllabus_chapters WHERE id = :id");
    $checkStmt->execute([':id' => $data['chapter_id']]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Chapter not found');
    }

    // Generate topic ID
    $id = generateTopicId($db);

    $stmt = $db->prepare("
        INSERT INTO syllabus_topics (id, chapter_id, topic_no, title, description, learning_objectives, estimated_minutes)
        VALUES (:id, :chapter_id, :topic_no, :title, :description, :learning_objectives, :estimated_minutes)
    ");

    $result = $stmt->execute([
        ':id' => $id,
        ':chapter_id' => $data['chapter_id'],
        ':topic_no' => (int)$data['topic_no'],
        ':title' => sanitizeInput($data['title']),
        ':description' => sanitizeInput($data['description'] ?? ''),
        ':learning_objectives' => sanitizeInput($data['learning_objectives'] ?? ''),
        ':estimated_minutes' => (int)($data['estimated_minutes'] ?? 45)
    ]);

    if ($result) {
        logActivity($db, getCurrentUserId(), 'Added topic: ' . $data['title'], 'Syllabus', [
            'topic_id' => $id,
            'chapter_id' => $data['chapter_id']
        ]);
        sendResponse(true, 'Topic added successfully', ['id' => $id]);
    } else {
        sendResponse(false, 'Failed to add topic');
    }
}

/**
 * Mark progress (complete/incomplete)
 */
function markProgress($db, $data) {
    $required = ['topic_id', 'class', 'section'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    $isCompleted = isset($data['is_completed']) ? (int)$data['is_completed'] : 1;

    // Check if progress record exists
    $checkStmt = $db->prepare("
        SELECT id FROM syllabus_progress
        WHERE topic_id = :topic_id AND class = :class AND section = :section
    ");
    $checkStmt->execute([
        ':topic_id' => $data['topic_id'],
        ':class' => $data['class'],
        ':section' => $data['section']
    ]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE syllabus_progress
            SET is_completed = :is_completed,
                completed_at = :completed_at,
                completed_by = :completed_by,
                remarks = :remarks
            WHERE id = :id
        ");
        $result = $stmt->execute([
            ':id' => $existing['id'],
            ':is_completed' => $isCompleted,
            ':completed_at' => $isCompleted ? date('Y-m-d H:i:s') : null,
            ':completed_by' => $isCompleted ? getCurrentUserId() : null,
            ':remarks' => sanitizeInput($data['remarks'] ?? '')
        ]);
    } else {
        // Create new record
        $stmt = $db->prepare("
            INSERT INTO syllabus_progress (topic_id, class, section, is_completed, completed_at, completed_by, remarks)
            VALUES (:topic_id, :class, :section, :is_completed, :completed_at, :completed_by, :remarks)
        ");
        $result = $stmt->execute([
            ':topic_id' => $data['topic_id'],
            ':class' => $data['class'],
            ':section' => $data['section'],
            ':is_completed' => $isCompleted,
            ':completed_at' => $isCompleted ? date('Y-m-d H:i:s') : null,
            ':completed_by' => $isCompleted ? getCurrentUserId() : null,
            ':remarks' => sanitizeInput($data['remarks'] ?? '')
        ]);
    }

    if ($result) {
        $status = $isCompleted ? 'completed' : 'marked incomplete';
        logActivity($db, getCurrentUserId(), "Topic $status", 'Syllabus', [
            'topic_id' => $data['topic_id'],
            'class' => $data['class'],
            'section' => $data['section']
        ]);
        sendResponse(true, 'Progress updated successfully');
    } else {
        sendResponse(false, 'Failed to update progress');
    }
}

// ============================================
// PUT FUNCTIONS
// ============================================

/**
 * Update syllabus
 */
function updateSyllabus($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $fields = [];
    $bindings = [':id' => $data['id']];

    $updateableFields = ['title', 'description', 'status', 'academic_year'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = :$field";
            $bindings[":$field"] = sanitizeInput($data[$field]);
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE syllabus SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($bindings);

    if ($result) {
        logActivity($db, getCurrentUserId(), 'Updated syllabus', 'Syllabus', ['syllabus_id' => $data['id']]);
        sendResponse(true, 'Syllabus updated successfully');
    } else {
        sendResponse(false, 'Failed to update syllabus');
    }
}

/**
 * Update chapter
 */
function updateChapter($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Chapter ID is required');
    }

    $fields = [];
    $bindings = [':id' => $data['id']];

    $updateableFields = ['title', 'description', 'chapter_no', 'estimated_hours'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = :$field";
            if ($field === 'chapter_no') {
                $bindings[":$field"] = (int)$data[$field];
            } elseif ($field === 'estimated_hours') {
                $bindings[":$field"] = (float)$data[$field];
            } else {
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE syllabus_chapters SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($bindings);

    if ($result) {
        logActivity($db, getCurrentUserId(), 'Updated chapter', 'Syllabus', ['chapter_id' => $data['id']]);
        sendResponse(true, 'Chapter updated successfully');
    } else {
        sendResponse(false, 'Failed to update chapter');
    }
}

/**
 * Update topic
 */
function updateTopic($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Topic ID is required');
    }

    $fields = [];
    $bindings = [':id' => $data['id']];

    $updateableFields = ['title', 'description', 'topic_no', 'learning_objectives', 'estimated_minutes'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = :$field";
            if ($field === 'topic_no' || $field === 'estimated_minutes') {
                $bindings[":$field"] = (int)$data[$field];
            } else {
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE syllabus_topics SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $result = $stmt->execute($bindings);

    if ($result) {
        logActivity($db, getCurrentUserId(), 'Updated topic', 'Syllabus', ['topic_id' => $data['id']]);
        sendResponse(true, 'Topic updated successfully');
    } else {
        sendResponse(false, 'Failed to update topic');
    }
}

// ============================================
// DELETE FUNCTIONS
// ============================================

/**
 * Delete syllabus
 */
function deleteSyllabus($db, $id) {
    // Chapters and topics will be deleted via CASCADE
    $stmt = $db->prepare("DELETE FROM syllabus WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);

    if ($result && $stmt->rowCount() > 0) {
        logActivity($db, getCurrentUserId(), 'Deleted syllabus', 'Syllabus', ['syllabus_id' => $id]);
        sendResponse(true, 'Syllabus deleted successfully');
    } else {
        sendResponse(false, 'Syllabus not found or already deleted');
    }
}

/**
 * Delete chapter
 */
function deleteChapter($db, $id) {
    // Topics will be deleted via CASCADE
    $stmt = $db->prepare("DELETE FROM syllabus_chapters WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);

    if ($result && $stmt->rowCount() > 0) {
        logActivity($db, getCurrentUserId(), 'Deleted chapter', 'Syllabus', ['chapter_id' => $id]);
        sendResponse(true, 'Chapter deleted successfully');
    } else {
        sendResponse(false, 'Chapter not found or already deleted');
    }
}

/**
 * Delete topic
 */
function deleteTopic($db, $id) {
    $stmt = $db->prepare("DELETE FROM syllabus_topics WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);

    if ($result && $stmt->rowCount() > 0) {
        logActivity($db, getCurrentUserId(), 'Deleted topic', 'Syllabus', ['topic_id' => $id]);
        sendResponse(true, 'Topic deleted successfully');
    } else {
        sendResponse(false, 'Topic not found or already deleted');
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generate unique syllabus ID
 */
function generateSyllabusId($db) {
    $stmt = $db->query("SELECT COUNT(*) as count FROM syllabus");
    $count = $stmt->fetch()['count'] + 1;
    return 'SYL' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique chapter ID
 */
function generateChapterId($db) {
    $stmt = $db->query("SELECT COUNT(*) as count FROM syllabus_chapters");
    $count = $stmt->fetch()['count'] + 1;
    return 'CHP' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate unique topic ID
 */
function generateTopicId($db) {
    $stmt = $db->query("SELECT COUNT(*) as count FROM syllabus_topics");
    $count = $stmt->fetch()['count'] + 1;
    return 'TOP' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Log syllabus activity
 */
function logSyllabusActivity($db, $syllabusId, $action, $details = '') {
    try {
        $userId = getCurrentUserId();
        $userName = getCurrentUserName();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = $db->prepare("
            INSERT INTO syllabus_activity_logs (syllabus_id, action, user_id, user_name, details, ip_address)
            VALUES (:syllabus_id, :action, :user_id, :user_name, :details, :ip_address)
        ");
        $stmt->execute([
            ':syllabus_id' => $syllabusId,
            ':action' => $action,
            ':user_id' => $userId,
            ':user_name' => $userName,
            ':details' => $details,
            ':ip_address' => $ip
        ]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

/**
 * Get current user name
 */
function getCurrentUserName() {
    return $_SESSION['user']['name'] ?? $_SESSION['user_name'] ?? 'Unknown';
}

/**
 * Save syllabus version
 */
function saveSyllabusVersion($db, $syllabusId, $data) {
    try {
        // Get current version
        $stmt = $db->prepare("SELECT version FROM syllabus WHERE id = :id");
        $stmt->execute([':id' => $syllabusId]);
        $row = $stmt->fetch();
        $version = $row ? (int)$row['version'] : 1;

        $stmt = $db->prepare("
            INSERT INTO syllabus_versions (syllabus_id, version, data, changed_by, change_summary)
            VALUES (:syllabus_id, :version, :data, :changed_by, :change_summary)
        ");
        $stmt->execute([
            ':syllabus_id' => $syllabusId,
            ':version' => $version,
            ':data' => json_encode($data),
            ':changed_by' => getCurrentUserId(),
            ':change_summary' => 'Version ' . $version . ' saved'
        ]);
    } catch (Exception $e) {
        // Silent fail
    }
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Upload syllabus file
 */
function uploadSyllabusFile($db, $data) {
    if (empty($data['syllabus_id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    if (empty($data['file']) || empty($data['file_name'])) {
        sendResponse(false, 'File data is required');
    }

    // Verify syllabus exists
    $checkStmt = $db->prepare("SELECT id FROM syllabus WHERE id = :id");
    $checkStmt->execute([':id' => $data['syllabus_id']]);
    if (!$checkStmt->fetch()) {
        sendResponse(false, 'Syllabus not found');
    }

    // Validate file type
    $allowedTypes = ['application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $fileType = $data['file_type'] ?? '';

    if (!in_array($fileType, $allowedTypes)) {
        sendResponse(false, 'Invalid file type. Only PDF and DOCX files are allowed');
    }

    // Decode base64 file
    $fileData = base64_decode($data['file']);
    if ($fileData === false) {
        sendResponse(false, 'Invalid file data');
    }

    // Check file size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if (strlen($fileData) > $maxSize) {
        sendResponse(false, 'File size exceeds 10MB limit');
    }

    // Generate unique filename
    $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('syllabus_') . '_' . time() . '.' . $extension;

    // Create upload directory
    $uploadDir = __DIR__ . '/../../uploads/syllabus/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filePath = $uploadDir . $newFileName;

    // Save file
    if (file_put_contents($filePath, $fileData) === false) {
        sendResponse(false, 'Failed to save file');
    }

    // Save to database
    $stmt = $db->prepare("
        INSERT INTO syllabus_files (syllabus_id, file_name, original_name, file_type, file_size, file_path, uploaded_by)
        VALUES (:syllabus_id, :file_name, :original_name, :file_type, :file_size, :file_path, :uploaded_by)
    ");

    $result = $stmt->execute([
        ':syllabus_id' => $data['syllabus_id'],
        ':file_name' => $newFileName,
        ':original_name' => sanitizeInput($data['file_name']),
        ':file_type' => $fileType,
        ':file_size' => strlen($fileData),
        ':file_path' => 'uploads/syllabus/' . $newFileName,
        ':uploaded_by' => getCurrentUserId()
    ]);

    if ($result) {
        logSyllabusActivity($db, $data['syllabus_id'], 'UPLOAD', 'Uploaded file: ' . $data['file_name']);
        sendResponse(true, 'File uploaded successfully', [
            'id' => $db->lastInsertId(),
            'file_name' => $newFileName,
            'original_name' => $data['file_name']
        ]);
    } else {
        // Remove file if DB insert failed
        unlink($filePath);
        sendResponse(false, 'Failed to save file record');
    }
}

/**
 * Get syllabus files
 */
function getSyllabusFiles($db, $params) {
    if (empty($params['syllabus_id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $stmt = $db->prepare("
        SELECT id, file_name, original_name, file_type, file_size, file_path, uploaded_by, uploaded_at
        FROM syllabus_files
        WHERE syllabus_id = :syllabus_id AND is_deleted = 0
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([':syllabus_id' => $params['syllabus_id']]);
    $files = $stmt->fetchAll();

    sendResponse(true, 'Files fetched successfully', ['files' => $files]);
}

/**
 * Delete syllabus file
 */
function deleteSyllabusFile($db, $id) {
    // Soft delete
    $stmt = $db->prepare("UPDATE syllabus_files SET is_deleted = 1 WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);

    if ($result && $stmt->rowCount() > 0) {
        sendResponse(true, 'File deleted successfully');
    } else {
        sendResponse(false, 'File not found');
    }
}

/**
 * Get syllabus activity logs
 */
function getSyllabusLogs($db, $params) {
    if (empty($params['syllabus_id'])) {
        sendResponse(false, 'Syllabus ID is required');
    }

    $stmt = $db->prepare("
        SELECT id, action, user_name, details, ip_address, created_at
        FROM syllabus_activity_logs
        WHERE syllabus_id = :syllabus_id
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':syllabus_id' => $params['syllabus_id']]);
    $logs = $stmt->fetchAll();

    sendResponse(true, 'Logs fetched successfully', ['logs' => $logs]);
}
