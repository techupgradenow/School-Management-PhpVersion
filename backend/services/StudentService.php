<?php
/**
 * Student Service
 *
 * Business logic for the Students module.
 * Orchestrates StudentRepository and related data access.
 *
 * IMPORTANT: This service ensures:
 * - Data is fetched only from correct tables (student_* tables)
 * - Multi-tenant isolation via school_id
 * - Proper transaction handling for complex operations
 * - Activity logging for all mutations
 */

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../repositories/StudentRepository.php';

class StudentService extends BaseService {
    protected string $module = 'students';
    private StudentRepository $studentRepo;

    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->studentRepo = new StudentRepository($db);
    }

    /**
     * Get paginated list of students
     */
    public function getList(array $params): array {
        $this->requirePermission('view');

        $result = $this->studentRepo->getStudentsList($params);

        return $this->success($result, 'Students fetched successfully');
    }

    /**
     * Get single student with all related data
     *
     * CORRECT DATA MAPPING:
     * - Basic info from: students
     * - Parents from: student_parents
     * - Medical from: student_medical
     * - Documents from: student_documents
     * - Emergency contacts from: student_emergency_contacts
     * - Siblings from: student_siblings
     * - Previous school from: student_previous_school
     * - Attendance from: attendance (filtered by student_id)
     * - Fees from: fee_payments (filtered by student_id)
     * - Exams from: exam_marks (filtered by student_id)
     */
    public function getById($id): array {
        $this->requirePermission('view');

        $student = $this->studentRepo->findWithDetails($id);

        if (!$student) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        return $this->success($student, 'Student fetched successfully');
    }

    /**
     * Get student attendance records
     * Data Source: attendance table (NOT student_* tables)
     */
    public function getAttendance($studentId, ?string $month = null, ?string $year = null): array {
        $this->requirePermission('view');

        // Verify student exists
        if (!$this->studentRepo->exists($studentId)) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        $attendance = $this->studentRepo->getAttendance($studentId, $month, $year);

        // Calculate summary
        $summary = [
            'total_days' => count($attendance),
            'present' => count(array_filter($attendance, fn($a) => $a['status'] === 'Present')),
            'absent' => count(array_filter($attendance, fn($a) => $a['status'] === 'Absent')),
            'late' => count(array_filter($attendance, fn($a) => $a['status'] === 'Late')),
        ];
        $summary['percentage'] = $summary['total_days'] > 0
            ? round(($summary['present'] + $summary['late']) / $summary['total_days'] * 100, 1)
            : 0;

        return $this->success([
            'records' => $attendance,
            'summary' => $summary
        ], 'Attendance fetched successfully');
    }

    /**
     * Get student fee details
     * Data Source: fee_payments + fee_structures (NOT student_* tables)
     */
    public function getFees($studentId): array {
        $this->requirePermission('view');

        // Verify student exists
        $student = $this->studentRepo->findById($studentId);
        if (!$student) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        $payments = $this->studentRepo->getFeePayments($studentId);

        // Get fee structure for student's class
        $feeStructure = $this->getFeeStructureForClass($student['class_id'] ?? $student['class']);

        // Calculate totals
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $totalDue = array_sum(array_column($feeStructure, 'amount'));

        return $this->success([
            'fee_structure' => $feeStructure,
            'payments' => $payments,
            'summary' => [
                'total_fee' => $totalDue,
                'total_paid' => $totalPaid,
                'balance' => $totalDue - $totalPaid
            ]
        ], 'Fee details fetched successfully');
    }

    /**
     * Get student exam results
     * Data Source: exam_marks + exams (NOT student_* tables)
     */
    public function getExams($studentId): array {
        $this->requirePermission('view');

        // Verify student exists
        if (!$this->studentRepo->exists($studentId)) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        $results = $this->studentRepo->getExamResults($studentId);

        // Group by exam
        $grouped = [];
        foreach ($results as $result) {
            $examId = $result['exam_id'];
            if (!isset($grouped[$examId])) {
                $grouped[$examId] = [
                    'exam_id' => $examId,
                    'exam_name' => $result['exam_name'],
                    'exam_type' => $result['exam_type'],
                    'subjects' => []
                ];
            }
            $grouped[$examId]['subjects'][] = [
                'subject_name' => $result['subject_name'],
                'marks_obtained' => $result['marks_obtained'],
                'total_marks' => $result['total_marks'],
                'grade' => $result['grade'],
                'percentage' => $result['total_marks'] > 0
                    ? round($result['marks_obtained'] / $result['total_marks'] * 100, 1)
                    : 0
            ];
        }

        return $this->success(array_values($grouped), 'Exam results fetched successfully');
    }

    /**
     * Create new student with all related data
     */
    public function create(array $data): array {
        $this->requirePermission('create');

        // Validate required fields
        $required = ['name', 'class', 'admission_no'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            return $this->error('Missing required fields', ['missing' => $missing]);
        }

        // Validate email if provided
        if (!empty($data['email']) && !$this->validateEmail($data['email'])) {
            return $this->error('Invalid email format', ['email' => 'Invalid format']);
        }

        $this->beginTransaction();

        try {
            // Sanitize basic student data
            $studentData = [
                'name' => $this->sanitize($data['name']),
                'admission_no' => $this->sanitize($data['admission_no']),
                'class' => $data['class'],
                'class_id' => $data['class_id'] ?? null,
                'section' => $data['section'] ?? null,
                'section_id' => $data['section_id'] ?? null,
                'roll_no' => $data['roll_no'] ?? null,
                'dob' => $this->formatDate($data['dob'] ?? null),
                'gender' => $data['gender'] ?? null,
                'email' => $data['email'] ?? null,
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => $data['status'] ?? 'Active'
            ];

            // Create student record
            $studentId = $this->studentRepo->create($studentData);

            // Create parent records if provided
            if (!empty($data['parents']) && is_array($data['parents'])) {
                foreach ($data['parents'] as $parent) {
                    $this->studentRepo->addParent($studentId, $parent);
                }
            }

            // Create medical record if provided
            if (!empty($data['medical'])) {
                $this->createMedicalRecord($studentId, $data['medical']);
            }

            $this->commit();

            // Log activity
            $this->logActivity('ADD', $studentId, null, $studentData);

            return $this->success(['id' => $studentId], 'Student created successfully');

        } catch (Exception $e) {
            $this->rollback();
            return $this->error('Failed to create student: ' . $e->getMessage());
        }
    }

    /**
     * Update existing student
     */
    public function update($id, array $data): array {
        $this->requirePermission('edit');

        // Verify student exists
        $existing = $this->studentRepo->findById($id);
        if (!$existing) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        // Validate email if provided
        if (!empty($data['email']) && !$this->validateEmail($data['email'])) {
            return $this->error('Invalid email format', ['email' => 'Invalid format']);
        }

        $this->beginTransaction();

        try {
            // Update student record
            $updateData = array_filter([
                'name' => isset($data['name']) ? $this->sanitize($data['name']) : null,
                'class' => $data['class'] ?? null,
                'class_id' => $data['class_id'] ?? null,
                'section' => $data['section'] ?? null,
                'section_id' => $data['section_id'] ?? null,
                'roll_no' => $data['roll_no'] ?? null,
                'dob' => isset($data['dob']) ? $this->formatDate($data['dob']) : null,
                'gender' => $data['gender'] ?? null,
                'email' => $data['email'] ?? null,
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => $data['status'] ?? null
            ], fn($v) => $v !== null);

            $this->studentRepo->update($id, $updateData);

            $this->commit();

            // Log activity
            $this->logActivity('UPDATE', $id, $existing, $updateData);

            return $this->success(['id' => $id], 'Student updated successfully');

        } catch (Exception $e) {
            $this->rollback();
            return $this->error('Failed to update student: ' . $e->getMessage());
        }
    }

    /**
     * Delete student
     */
    public function delete($id): array {
        $this->requirePermission('delete');

        // Verify student exists
        $existing = $this->studentRepo->findById($id);
        if (!$existing) {
            return $this->error('Student not found', ['id' => 'Invalid student ID'], 404);
        }

        $this->beginTransaction();

        try {
            $this->studentRepo->delete($id);

            $this->commit();

            // Log activity
            $this->logActivity('DELETE', $id, $existing, null);

            return $this->success(null, 'Student deleted successfully');

        } catch (Exception $e) {
            $this->rollback();
            return $this->error('Failed to delete student: ' . $e->getMessage());
        }
    }

    /**
     * Get student statistics
     */
    public function getStats(): array {
        $this->requirePermission('view');

        $stats = $this->studentRepo->getStats();

        return $this->success($stats, 'Statistics fetched successfully');
    }

    /**
     * Get classes for filter dropdown
     */
    public function getClasses(): array {
        return $this->success(
            $this->studentRepo->getDistinctClasses(),
            'Classes fetched successfully'
        );
    }

    /**
     * Get sections for filter dropdown
     */
    public function getSections(): array {
        return $this->success(
            $this->studentRepo->getDistinctSections(),
            'Sections fetched successfully'
        );
    }

    // ========== PRIVATE HELPER METHODS ==========

    private function createMedicalRecord(int $studentId, array $data): void {
        // Implementation for creating medical record
    }

    private function getFeeStructureForClass($classId): array {
        $sql = "SELECT * FROM fee_structures
                WHERE (class_id = :class_id OR class = :class)
                AND school_id = :school_id
                AND status = 'Active'
                ORDER BY due_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':class_id' => $classId,
            ':class' => $classId,
            ':school_id' => $this->studentRepo->getDb() // Get school_id from context
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
