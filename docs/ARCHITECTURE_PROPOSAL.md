# School Management System - Architecture Proposal

## Executive Summary

This document provides a comprehensive architectural analysis and recommendations for fixing data mapping issues in the School Management System. The goal is to ensure each module uses only its correctly mapped data with proper isolation.

---

## 1. HIGH-LEVEL SYSTEM ARCHITECTURE

```
                                    ┌─────────────────────────────────────────────┐
                                    │              PRESENTATION LAYER              │
                                    │  (HTML/CSS/JavaScript - Frontend Pages)     │
                                    └─────────────────────────────────────────────┘
                                                          │
                                                          ▼
                                    ┌─────────────────────────────────────────────┐
                                    │               API GATEWAY                    │
                                    │  (Authentication, Rate Limiting, CORS)       │
                                    └─────────────────────────────────────────────┘
                                                          │
                    ┌─────────────────────────────────────┼─────────────────────────────────────┐
                    │                                     │                                     │
                    ▼                                     ▼                                     ▼
    ┌───────────────────────────┐     ┌───────────────────────────┐     ┌───────────────────────────┐
    │    ACADEMIC MODULE        │     │   ADMINISTRATION MODULE   │     │   SUPPORT SERVICES        │
    │                           │     │                           │     │                           │
    │  • Students               │     │  • Users                  │     │  • Transport              │
    │  • Teachers               │     │  • Payroll                │     │  • Hostel                 │
    │  • Classes/Sections       │     │  • Fees                   │     │  • Library                │
    │  • Attendance             │     │  • Expenses               │     │  • Events                 │
    │  • Exams/Marks            │     │  • Reports                │     │  • Notifications          │
    │  • Timetable              │     │  • Settings               │     │                           │
    │  • Syllabus               │     │                           │     │                           │
    └───────────────────────────┘     └───────────────────────────┘     └───────────────────────────┘
                    │                                     │                                     │
                    └─────────────────────────────────────┼─────────────────────────────────────┘
                                                          │
                                                          ▼
                                    ┌─────────────────────────────────────────────┐
                                    │              SERVICE LAYER                   │
                                    │  (Business Logic, Validation, Rules)         │
                                    └─────────────────────────────────────────────┘
                                                          │
                                                          ▼
                                    ┌─────────────────────────────────────────────┐
                                    │            REPOSITORY LAYER                  │
                                    │  (Data Access, Query Building)               │
                                    └─────────────────────────────────────────────┘
                                                          │
                                                          ▼
                                    ┌─────────────────────────────────────────────┐
                                    │            DATABASE LAYER                    │
                                    │  (MySQL - Multi-tenant with school_id)       │
                                    └─────────────────────────────────────────────┘
```

---

## 2. DATABASE ARCHITECTURE

### 2.1 Multi-Tenant Data Isolation

Every domain table MUST include `school_id` as the first foreign key. This is critical for data isolation.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           MULTI-TENANT HIERARCHY                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│   GLOBAL TABLES (No school_id)          TENANT TABLES (Requires school_id)      │
│   ═══════════════════════════           ══════════════════════════════════      │
│   • plans                               • students                               │
│   • features                            • teachers                               │
│   • plan_features                       • classes                                │
│   • roles (global templates)            • sections                               │
│   • permissions                         • subjects                               │
│   • global_users                        • attendance                             │
│   • schools                             • exams / exam_marks                     │
│                                         • fee_structures / fee_payments          │
│                                         • timetable                              │
│                                         • syllabus                               │
│                                         • transport_*                            │
│                                         • hostel_*                               │
│                                         • library_*                              │
│                                         • payroll                                │
│                                         • notifications                          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Module-to-Table Mapping

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          MODULE → TABLE MAPPING                                  │
├─────────────────┬───────────────────────────────────────────────────────────────┤
│ MODULE          │ TABLES                                                        │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ STUDENTS        │ students (master)                                             │
│                 │ ├── student_parents                                           │
│                 │ ├── student_emergency_contacts                                │
│                 │ ├── student_medical                                           │
│                 │ ├── student_siblings                                          │
│                 │ ├── student_previous_school                                   │
│                 │ ├── student_documents                                         │
│                 │ └── student_fee_discounts                                     │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ TEACHERS        │ teachers (master)                                             │
│                 │ ├── teacher_qualifications                                    │
│                 │ ├── teacher_documents                                         │
│                 │ ├── teacher_absences                                          │
│                 │ ├── teacher_substitutions                                     │
│                 │ └── teacher_subjects (pivot)                                  │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ CLASSES         │ classes (master)                                              │
│                 │ ├── sections                                                  │
│                 │ └── class_subjects (pivot)                                    │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ ATTENDANCE      │ attendance (student attendance)                               │
│                 │ ├── teacher_attendance                                        │
│                 │ └── staff_attendance                                          │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ EXAMS           │ exam_types (master: Unit Test, Midterm, Final)                │
│                 │ ├── exams (specific exam instances)                           │
│                 │ ├── exam_subjects (subjects in exam)                          │
│                 │ ├── exam_marks (student results)                              │
│                 │ └── grade_scales (grading rules)                              │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ FEES            │ fee_types (master: Tuition, Transport, Hostel)                │
│                 │ ├── fee_structures (class-wise fee setup)                     │
│                 │ ├── fee_payments (actual payments)                            │
│                 │ ├── fee_discounts (discount definitions)                      │
│                 │ └── student_fee_discounts (applied discounts)                 │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ TIMETABLE       │ timetable_periods (period definitions)                        │
│                 │ └── timetable (actual schedule)                               │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ SYLLABUS        │ syllabus (master)                                             │
│                 │ ├── syllabus_chapters                                         │
│                 │ ├── syllabus_topics                                           │
│                 │ └── syllabus_progress                                         │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ TRANSPORT       │ transport_vehicles (buses)                                    │
│                 │ ├── transport_routes                                          │
│                 │ ├── transport_stops                                           │
│                 │ └── transport_assignments (student to route)                  │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ HOSTEL          │ hostel_blocks                                                 │
│                 │ ├── hostel_rooms                                              │
│                 │ └── hostel_allocations (student to room)                      │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ LIBRARY         │ library_categories                                            │
│                 │ ├── library_books                                             │
│                 │ ├── library_issues                                            │
│                 │ └── library_fines                                             │
├─────────────────┼───────────────────────────────────────────────────────────────┤
│ PAYROLL         │ salary_structures                                             │
│                 │ ├── payroll (monthly calculations)                            │
│                 │ └── payroll_deductions                                        │
└─────────────────┴───────────────────────────────────────────────────────────────┘
```

---

## 3. CORRECT DATA RELATIONSHIPS (ERD)

### 3.1 Student Module Relationships

```
                                    ┌─────────────────┐
                                    │     schools     │
                                    │─────────────────│
                                    │ PK: id (UUID)   │
                                    │    name         │
                                    │    code         │
                                    └────────┬────────┘
                                             │
                                             │ 1:N
                                             ▼
┌──────────────────────┐          ┌─────────────────────┐          ┌──────────────────────┐
│  student_documents   │          │      students       │          │   student_parents    │
│──────────────────────│          │─────────────────────│          │──────────────────────│
│ PK: id (UUID)        │          │ PK: id (INT/UUID)   │          │ PK: id (UUID)        │
│ FK: school_id ──────────────────│ FK: school_id ◄─────│──────────│ FK: school_id        │
│ FK: student_id ◄─────│──────────│ FK: class_id        │          │ FK: student_id ◄─────│
│    document_type     │          │ FK: section_id      │          │    relation          │
│    file_path         │          │    admission_no     │          │    name              │
└──────────────────────┘          │    name             │          │    phone             │
                                  │    dob              │          │    email             │
┌──────────────────────┐          │    gender           │          │    occupation        │
│   student_medical    │          │    status           │          │    is_primary        │
│──────────────────────│          └─────────┬───────────┘          └──────────────────────┘
│ PK: id (UUID)        │                    │
│ FK: school_id ───────│────────────────────│
│ FK: student_id ◄─────│────────────────────┤
│    blood_group       │                    │
│    allergies         │          ┌─────────┴────────────┐
│    medical_conditions│          │                      │
└──────────────────────┘          ▼                      ▼
                        ┌──────────────────┐   ┌───────────────────────┐
                        │    attendance    │   │      fee_payments     │
                        │──────────────────│   │───────────────────────│
                        │ PK: id (UUID)    │   │ PK: id (UUID)         │
                        │ FK: school_id    │   │ FK: school_id         │
                        │ FK: student_id   │   │ FK: student_id        │
                        │ FK: class_id     │   │ FK: fee_structure_id  │
                        │    date          │   │    amount             │
                        │    status        │   │    payment_date       │
                        └──────────────────┘   │    payment_mode       │
                                               └───────────────────────┘
```

### 3.2 Academic Relationships

```
┌─────────────────┐          ┌─────────────────┐          ┌─────────────────┐
│    classes      │ 1:N      │    sections     │          │    subjects     │
│─────────────────│ ────────►│─────────────────│          │─────────────────│
│ PK: id          │          │ PK: id          │          │ PK: id          │
│ FK: school_id   │          │ FK: school_id   │          │ FK: school_id   │
│    name         │          │ FK: class_id    │          │    name         │
│    grade_level  │          │    name         │          │    code         │
└────────┬────────┘          └─────────────────┘          └────────┬────────┘
         │                                                          │
         │                   ┌─────────────────┐                    │
         │        N:M        │  class_subjects │         N:M        │
         └──────────────────►│─────────────────│◄───────────────────┘
                             │ FK: class_id    │
                             │ FK: subject_id  │
                             │ FK: teacher_id  │
                             └─────────────────┘

┌─────────────────┐          ┌─────────────────┐          ┌─────────────────┐
│   exam_types    │          │      exams      │          │   exam_marks    │
│─────────────────│ 1:N      │─────────────────│ 1:N      │─────────────────│
│ PK: id          │ ────────►│ PK: id          │ ────────►│ PK: id          │
│ FK: school_id   │          │ FK: school_id   │          │ FK: school_id   │
│    name         │          │ FK: exam_type_id│          │ FK: exam_id     │
│    weightage    │          │ FK: class_id    │          │ FK: student_id  │
└─────────────────┘          │ FK: academic_yr │          │ FK: subject_id  │
                             │    name         │          │    marks_obtained│
                             │    date         │          │    grade        │
                             │    total_marks  │          │    remarks      │
                             └─────────────────┘          └─────────────────┘
```

---

## 4. SERVICE LAYER PATTERN

### 4.1 Proposed Directory Structure

```
backend/
├── api/                           # API Endpoints (Thin Controllers)
│   ├── students.php               # Routes to StudentService
│   ├── teachers.php               # Routes to TeacherService
│   ├── fees.php                   # Routes to FeeService
│   └── ...
├── services/                      # Business Logic Layer (NEW)
│   ├── BaseService.php            # Abstract base with common methods
│   ├── StudentService.php         # Student business logic
│   ├── TeacherService.php         # Teacher business logic
│   ├── AttendanceService.php      # Attendance business logic
│   ├── FeeService.php             # Fee business logic
│   ├── ExamService.php            # Exam business logic
│   ├── TimetableService.php       # Timetable business logic
│   └── ...
├── repositories/                  # Data Access Layer (NEW)
│   ├── BaseRepository.php         # Abstract base with CRUD
│   ├── StudentRepository.php      # Student data access
│   ├── TeacherRepository.php      # Teacher data access
│   ├── AttendanceRepository.php   # Attendance data access
│   └── ...
├── helpers/                       # Utilities
│   ├── TenantContext.php          # Multi-tenant context
│   ├── PermissionManager.php      # RBAC
│   ├── ActivityLogger.php         # Audit logging
│   └── functions.php              # Helper functions
└── config/
    ├── db.php                     # Database connection
    └── env.php                    # Environment config
```

### 4.2 Base Repository Pattern

```php
<?php
// backend/repositories/BaseRepository.php

abstract class BaseRepository {
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected ?string $schoolId = null;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->schoolId = TenantContext::getInstance()->getSchoolId();
    }

    // All queries automatically filter by school_id
    protected function getBaseQuery(): string {
        return "SELECT * FROM {$this->table} WHERE school_id = :school_id";
    }

    public function findById($id): ?array {
        $sql = $this->getBaseQuery() . " AND {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':school_id' => $this->schoolId, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array {
        $sql = $this->getBaseQuery();
        $params = [':school_id' => $this->schoolId];

        foreach ($filters as $field => $value) {
            $sql .= " AND {$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql .= " LIMIT :offset, :limit";
        $params[':offset'] = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): string {
        $data['school_id'] = $this->schoolId;
        $data['id'] = $this->generateUuid();
        $data['created_at'] = date('Y-m-d H:i:s');

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $data['id'];
    }

    public function update($id, array $data): bool {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $sets = [];
        foreach (array_keys($data) as $field) {
            $sets[] = "{$field} = :{$field}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) .
               " WHERE {$this->primaryKey} = :id AND school_id = :school_id";

        $data['id'] = $id;
        $data['school_id'] = $this->schoolId;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id): bool {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id AND school_id = :school_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':school_id' => $this->schoolId]);
    }

    protected function generateUuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
```

### 4.3 Service Layer Example

```php
<?php
// backend/services/StudentService.php

class StudentService {
    private StudentRepository $studentRepo;
    private StudentParentRepository $parentRepo;
    private StudentMedicalRepository $medicalRepo;
    private ActivityLogger $logger;

    public function __construct(PDO $db) {
        $this->studentRepo = new StudentRepository($db);
        $this->parentRepo = new StudentParentRepository($db);
        $this->medicalRepo = new StudentMedicalRepository($db);
        $this->logger = new ActivityLogger($db);
    }

    // Get student with ALL related data
    public function getStudentWithDetails($studentId): ?array {
        $student = $this->studentRepo->findById($studentId);

        if (!$student) {
            return null;
        }

        // Aggregate related data from correct tables
        $student['parents'] = $this->parentRepo->findByStudentId($studentId);
        $student['medical'] = $this->medicalRepo->findByStudentId($studentId);
        $student['emergency_contacts'] = $this->getEmergencyContacts($studentId);
        $student['siblings'] = $this->getSiblings($studentId);
        $student['documents'] = $this->getDocuments($studentId);

        return $student;
    }

    // Create student with all related data in transaction
    public function createStudent(array $data): array {
        $db = $this->studentRepo->getDb();
        $db->beginTransaction();

        try {
            // Create main student record
            $studentId = $this->studentRepo->create($data['student']);

            // Create parent records
            if (!empty($data['parents'])) {
                foreach ($data['parents'] as $parent) {
                    $parent['student_id'] = $studentId;
                    $this->parentRepo->create($parent);
                }
            }

            // Create medical record
            if (!empty($data['medical'])) {
                $data['medical']['student_id'] = $studentId;
                $this->medicalRepo->create($data['medical']);
            }

            $db->commit();

            // Log activity
            $this->logger->log('students', 'ADD', $studentId, null, $data);

            return ['success' => true, 'id' => $studentId];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
```

---

## 5. API-to-UI MAPPING

### 5.1 Correct API Endpoint Structure

```
┌────────────────────────────────────────────────────────────────────────────────┐
│                          API → UI MAPPING                                       │
├────────────────────────┬───────────────────────┬───────────────────────────────┤
│ FRONTEND PAGE          │ API ENDPOINT          │ DATA SOURCE (Tables)          │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ students.html          │ GET /students.php     │ students                      │
│                        │   ?action=list        │                               │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ student-detail.html    │ GET /students.php     │ students                      │
│                        │   ?action=single      │ + student_parents             │
│                        │   &id=X               │ + student_medical             │
│                        │                       │ + student_documents           │
│                        │                       │ + student_emergency_contacts  │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /students.php     │ attendance                    │
│                        │   ?action=attendance  │ (WHERE student_id = X)        │
│                        │   &student_id=X       │                               │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /students.php     │ fee_payments                  │
│                        │   ?action=fees        │ + fee_structures              │
│                        │   &student_id=X       │ (WHERE student_id = X)        │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /students.php     │ exam_marks                    │
│                        │   ?action=exams       │ + exams                       │
│                        │   &student_id=X       │ (WHERE student_id = X)        │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ teachers.html          │ GET /teachers.php     │ teachers                      │
│                        │   ?action=list        │                               │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ teacher-detail.html    │ GET /teachers.php     │ teachers                      │
│                        │   ?action=single      │ + teacher_qualifications      │
│                        │   &id=X               │ + teacher_documents           │
│                        │                       │ + teacher_subjects            │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ attendance.html        │ GET /attendance.php   │ attendance                    │
│                        │   ?class=X            │ + students                    │
│                        │   &section=Y          │ (JOIN for student names)      │
│                        │   &date=Z             │                               │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ fees.html              │ GET /fees.php         │ fee_structures                │
│                        │   ?action=structures  │                               │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /fees.php         │ fee_payments                  │
│                        │   ?action=payments    │ + students (JOIN)             │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ exams.html             │ GET /exams.php        │ exams                         │
│                        │   ?action=list        │ + exam_types                  │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ marks-entry.html       │ GET /exam_marks.php   │ exam_marks                    │
│                        │   ?exam_id=X          │ + students                    │
│                        │   &subject_id=Y       │ + subjects                    │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ timetable.html         │ GET /timetable.php    │ timetable                     │
│                        │   ?class=X            │ + teachers (JOIN)             │
│                        │   &section=Y          │ + subjects (JOIN)             │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ transport.html         │ GET /transport.php    │ transport_routes              │
│                        │   ?action=routes      │ + transport_stops             │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /transport.php    │ transport_assignments         │
│                        │   ?action=assignments │ + students (JOIN)             │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ hostel.html            │ GET /hostel.php       │ hostel_blocks                 │
│                        │   ?action=blocks      │ + hostel_rooms                │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /hostel.php       │ hostel_allocations            │
│                        │   ?action=allocations │ + students (JOIN)             │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ library.html           │ GET /library.php      │ library_books                 │
│                        │   ?action=books       │ + library_categories          │
│                        ├───────────────────────┼───────────────────────────────┤
│                        │ GET /library.php      │ library_issues                │
│                        │   ?action=issues      │ + students/teachers (JOIN)    │
├────────────────────────┼───────────────────────┼───────────────────────────────┤
│ payroll.html           │ GET /payroll.php      │ payroll                       │
│                        │   ?month=X            │ + teachers/staff (JOIN)       │
│                        │   &year=Y             │                               │
└────────────────────────┴───────────────────────┴───────────────────────────────┘
```

---

## 6. COMMON DATA MAPPING ISSUES & FIXES

### 6.1 Issue: Cross-Module Data Leakage

**Problem**: Student data appearing in Teacher module or vice versa.

**Root Cause**: Shared or incorrect table queries.

**Fix**:
```php
// WRONG - No module separation
$stmt = $db->query("SELECT * FROM users");

// CORRECT - Module-specific query
$stmt = $db->prepare("SELECT * FROM students WHERE school_id = ?");
$stmt->execute([$schoolId]);
```

### 6.2 Issue: Missing school_id Filter

**Problem**: Data from other schools visible.

**Fix**:
```php
// ALWAYS include school_id in WHERE clause
class BaseRepository {
    protected function addSchoolFilter(string $sql): string {
        // Automatically add school_id filter
        if (strpos($sql, 'WHERE') !== false) {
            return str_replace('WHERE', "WHERE school_id = '{$this->schoolId}' AND", $sql);
        }
        return $sql . " WHERE school_id = '{$this->schoolId}'";
    }
}
```

### 6.3 Issue: Incorrect Foreign Key References

**Problem**: Fee payments showing for wrong students.

**Root Cause**: Missing or incorrect student_id join.

**Fix**:
```sql
-- WRONG
SELECT * FROM fee_payments;

-- CORRECT
SELECT fp.*, s.name as student_name
FROM fee_payments fp
INNER JOIN students s ON fp.student_id = s.id AND fp.school_id = s.school_id
WHERE fp.school_id = ? AND fp.student_id = ?;
```

### 6.4 Issue: Attendance Records Mixed

**Problem**: Teacher attendance mixed with student attendance.

**Fix**: Use separate tables:
```sql
-- Student attendance
CREATE TABLE attendance (
    student_id INT NOT NULL,
    -- ...
);

-- Teacher attendance (separate table)
CREATE TABLE teacher_attendance (
    teacher_id INT NOT NULL,
    -- ...
);
```

---

## 7. REFACTORING STRATEGY

### Phase 1: Database Cleanup (Week 1-2)
1. Add `school_id` to ALL domain tables (if missing)
2. Add proper indexes on foreign keys
3. Verify all relationships with foreign key constraints
4. Clean up orphaned records

### Phase 2: Repository Layer (Week 3-4)
1. Create `BaseRepository` class
2. Create module-specific repositories
3. Migrate all direct database queries to repositories
4. Add automatic `school_id` filtering

### Phase 3: Service Layer (Week 5-6)
1. Create `BaseService` class
2. Create module-specific services
3. Move business logic from API files to services
4. Implement proper validation and error handling

### Phase 4: API Refactoring (Week 7-8)
1. Update API files to use services
2. Standardize response formats
3. Implement proper error handling
4. Add request validation

### Phase 5: Frontend Updates (Week 9-10)
1. Update API calls to use correct endpoints
2. Remove all mock/demo data
3. Add proper error handling
4. Test all data mappings

---

## 8. VALIDATION CHECKLIST

Before deploying, verify:

- [ ] Every table has `school_id` column
- [ ] Every query filters by `school_id`
- [ ] Every foreign key is properly indexed
- [ ] No cross-module data queries
- [ ] Activity logging enabled for all changes
- [ ] RBAC enforced at API level
- [ ] No mock/demo data in production
- [ ] All API endpoints return consistent format
- [ ] Frontend only calls correct endpoints for each section

---

## 9. RECOMMENDED DATABASE INDEXES

```sql
-- Students module
CREATE INDEX idx_students_school_class ON students(school_id, class_id);
CREATE INDEX idx_students_school_status ON students(school_id, status);

-- Attendance module
CREATE INDEX idx_attendance_student_date ON attendance(school_id, student_id, date);
CREATE INDEX idx_attendance_class_date ON attendance(school_id, class_id, date);

-- Fees module
CREATE INDEX idx_fee_payments_student ON fee_payments(school_id, student_id);
CREATE INDEX idx_fee_payments_date ON fee_payments(school_id, payment_date);

-- Exams module
CREATE INDEX idx_exam_marks_student ON exam_marks(school_id, student_id);
CREATE INDEX idx_exam_marks_exam ON exam_marks(school_id, exam_id);

-- Timetable module
CREATE INDEX idx_timetable_class ON timetable(school_id, class_id, day);
```

---

## 10. SUMMARY

The key principles for a correctly architected School Management System:

1. **Multi-tenant Isolation**: Every domain table must have `school_id`
2. **Module Separation**: Each module has its own set of tables
3. **Repository Pattern**: Centralized data access with automatic filtering
4. **Service Layer**: Business logic separate from API controllers
5. **Proper Foreign Keys**: All relationships explicitly defined
6. **Consistent API**: Standard request/response format
7. **RBAC**: Permission checks at API level
8. **Audit Logging**: All changes tracked with user context
9. **No Mock Data**: Production uses only database data
10. **Proper Indexing**: Optimized queries for common patterns
