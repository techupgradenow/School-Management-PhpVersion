<?php
/**
 * Student Repository
 *
 * Handles all data access for the Students module.
 * Properly isolates student data from other modules.
 *
 * TABLES MANAGED:
 * - students (primary)
 * - student_parents
 * - student_emergency_contacts
 * - student_medical
 * - student_siblings
 * - student_previous_school
 * - student_documents
 */

require_once __DIR__ . '/BaseRepository.php';

class StudentRepository extends BaseRepository {
    protected string $table = 'students';
    protected string $primaryKey = 'id';

    /**
     * Find students with related data
     */
    public function findWithDetails($id): ?array {
        $student = $this->findById($id);

        if (!$student) {
            return null;
        }

        // Attach related data from correct child tables
        $student['parents'] = $this->getParents($id);
        $student['emergency_contacts'] = $this->getEmergencyContacts($id);
        $student['medical'] = $this->getMedical($id);
        $student['siblings'] = $this->getSiblings($id);
        $student['previous_school'] = $this->getPreviousSchool($id);
        $student['documents'] = $this->getDocuments($id);

        return $student;
    }

    /**
     * Get students with pagination and filters
     */
    public function getStudentsList(array $params): array {
        $page = $params['page'] ?? 1;
        $perPage = $params['perPage'] ?? 10;
        $search = $params['search'] ?? '';
        $class = $params['class'] ?? '';
        $section = $params['section'] ?? '';
        $status = $params['status'] ?? '';

        $sql = "SELECT s.*, c.name as class_name, sec.name as section_name
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE s.school_id = :school_id";
        $params = [':school_id' => $this->schoolId];

        if ($search) {
            $sql .= " AND (s.name LIKE :search OR s.admission_no LIKE :search OR s.contact LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if ($class) {
            $sql .= " AND (s.class_id = :class OR c.name = :class_name)";
            $params[':class'] = $class;
            $params[':class_name'] = $class;
        }

        if ($section) {
            $sql .= " AND (s.section_id = :section OR sec.name = :section_name)";
            $params[':section'] = $section;
            $params[':section_name'] = $section;
        }

        if ($status) {
            $sql .= " AND s.status = :status";
            $params[':status'] = $status;
        }

        // Count total
        $countSql = str_replace('SELECT s.*, c.name as class_name, sec.name as section_name', 'SELECT COUNT(*)', $sql);
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " ORDER BY s.name ASC LIMIT {$offset}, {$perPage}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $students,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get parent/guardian records for a student
     */
    public function getParents(int $studentId): array {
        $sql = "SELECT * FROM student_parents
                WHERE student_id = :student_id AND school_id = :school_id
                ORDER BY is_primary_contact DESC, relation ASC";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Add parent record for a student
     */
    public function addParent(int $studentId, array $data): string {
        $data['student_id'] = $studentId;
        $data['school_id'] = $this->schoolId;
        $data['id'] = $this->generateUuid();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['created_by'] = $this->getCurrentUser();

        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_map(fn($col) => ":{$col}", array_keys($data)));

        $sql = "INSERT INTO student_parents ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $data['id'];
    }

    /**
     * Get emergency contacts for a student
     */
    public function getEmergencyContacts(int $studentId): array {
        $sql = "SELECT * FROM student_emergency_contacts
                WHERE student_id = :student_id AND school_id = :school_id
                ORDER BY priority ASC";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Get medical information for a student
     */
    public function getMedical(int $studentId): ?array {
        $sql = "SELECT * FROM student_medical
                WHERE student_id = :student_id AND school_id = :school_id
                LIMIT 1";

        $results = $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
        return $results[0] ?? null;
    }

    /**
     * Get siblings for a student
     */
    public function getSiblings(int $studentId): array {
        $sql = "SELECT ss.*, s.name as sibling_name_in_school, s.class as sibling_class_in_school
                FROM student_siblings ss
                LEFT JOIN students s ON ss.sibling_student_id = s.id
                WHERE ss.student_id = :student_id AND ss.school_id = :school_id";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Get previous school information for a student
     */
    public function getPreviousSchool(int $studentId): ?array {
        $sql = "SELECT * FROM student_previous_school
                WHERE student_id = :student_id AND school_id = :school_id
                LIMIT 1";

        $results = $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
        return $results[0] ?? null;
    }

    /**
     * Get documents for a student
     */
    public function getDocuments(int $studentId): array {
        $sql = "SELECT * FROM student_documents
                WHERE student_id = :student_id AND school_id = :school_id
                ORDER BY uploaded_at DESC";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Get attendance records for a student
     * NOTE: This queries the attendance table, NOT a student sub-table
     */
    public function getAttendance(int $studentId, ?string $month = null, ?string $year = null): array {
        $sql = "SELECT * FROM attendance
                WHERE student_id = :student_id AND school_id = :school_id";
        $params = [':student_id' => $studentId, ':school_id' => $this->schoolId];

        if ($month) {
            $sql .= " AND MONTH(date) = :month";
            $params[':month'] = $month;
        }

        if ($year) {
            $sql .= " AND YEAR(date) = :year";
            $params[':year'] = $year;
        }

        $sql .= " ORDER BY date DESC";

        return $this->query($sql, $params);
    }

    /**
     * Get fee payments for a student
     * NOTE: This queries the fee_payments table
     */
    public function getFeePayments(int $studentId): array {
        $sql = "SELECT fp.*, fs.fee_name, fs.fee_type
                FROM fee_payments fp
                LEFT JOIN fee_structures fs ON fp.fee_structure_id = fs.id
                WHERE fp.student_id = :student_id AND fp.school_id = :school_id
                ORDER BY fp.payment_date DESC";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Get exam results for a student
     * NOTE: This queries the exam_marks table
     */
    public function getExamResults(int $studentId): array {
        $sql = "SELECT em.*, e.name as exam_name, e.type as exam_type, s.name as subject_name
                FROM exam_marks em
                JOIN exams e ON em.exam_id = e.id
                LEFT JOIN subjects s ON em.subject_id = s.id
                WHERE em.student_id = :student_id AND em.school_id = :school_id
                ORDER BY e.date DESC, s.name ASC";

        return $this->query($sql, [':student_id' => $studentId, ':school_id' => $this->schoolId]);
    }

    /**
     * Get distinct classes from students (for filters)
     */
    public function getDistinctClasses(): array {
        // First try classes table
        $sql = "SELECT id, name FROM classes WHERE school_id = :school_id ORDER BY name";
        $classes = $this->query($sql, [':school_id' => $this->schoolId]);

        if (!empty($classes)) {
            return $classes;
        }

        // Fallback to distinct from students
        $sql = "SELECT DISTINCT class as name FROM students
                WHERE school_id = :school_id AND class IS NOT NULL AND class != ''
                ORDER BY class";

        return $this->query($sql, [':school_id' => $this->schoolId]);
    }

    /**
     * Get distinct sections (for filters)
     */
    public function getDistinctSections(): array {
        // First try sections table
        $sql = "SELECT id, name FROM sections WHERE school_id = :school_id ORDER BY name";
        $sections = $this->query($sql, [':school_id' => $this->schoolId]);

        if (!empty($sections)) {
            return $sections;
        }

        // Fallback to distinct from students
        $sql = "SELECT DISTINCT section as name FROM students
                WHERE school_id = :school_id AND section IS NOT NULL AND section != ''
                ORDER BY section";

        return $this->query($sql, [':school_id' => $this->schoolId]);
    }

    /**
     * Get student statistics
     */
    public function getStats(): array {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
                    SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female
                FROM students
                WHERE school_id = :school_id";

        $result = $this->query($sql, [':school_id' => $this->schoolId]);
        return $result[0] ?? [];
    }
}
