# Quick Reference: School Management System Architecture

## 1. GOLDEN RULES (Must Follow)

### Rule 1: Always Filter by school_id
```php
// EVERY query to domain tables MUST include school_id
$stmt = $db->prepare("SELECT * FROM students WHERE school_id = ?");
$stmt->execute([$schoolId]);
```

### Rule 2: Use Correct Tables for Each Module
| Module | Primary Table | Related Tables |
|--------|---------------|----------------|
| Students | students | student_parents, student_medical, student_documents |
| Teachers | teachers | teacher_qualifications, teacher_absences |
| Attendance | attendance | (Linked to students via student_id) |
| Fees | fee_structures | fee_payments (Linked to students via student_id) |
| Exams | exams | exam_marks (Linked to students via student_id) |

### Rule 3: API Endpoint Naming Convention
```
GET  /module.php?action=list         → Get all records
GET  /module.php?action=single&id=X  → Get single record
POST /module.php                      → Create new record
PUT  /module.php                      → Update existing record
DELETE /module.php?id=X               → Delete record
```

---

## 2. FILE LOCATIONS

```
backend/
├── api/                    # API endpoints (thin controllers)
├── services/               # Business logic (NEW)
│   ├── BaseService.php
│   └── StudentService.php
├── repositories/           # Data access (NEW)
│   ├── BaseRepository.php
│   └── StudentRepository.php
├── helpers/                # Utilities
│   ├── TenantContext.php   # Multi-tenant context
│   ├── PermissionManager.php
│   └── ActivityLogger.php
└── config/
    ├── db.php              # Database connection
    └── env.php             # Environment config

frontend/
├── pages/                  # HTML pages
└── assets/                 # CSS, JS, Images
```

---

## 3. COMMON ISSUES & FIXES

### Issue: Student data showing teacher information
**Cause**: Wrong table being queried
```php
// WRONG
$stmt = $db->query("SELECT * FROM users WHERE role = 'student'");

// CORRECT
$stmt = $db->prepare("SELECT * FROM students WHERE school_id = ?");
```

### Issue: Data from other schools visible
**Cause**: Missing school_id filter
```php
// WRONG
$stmt = $db->query("SELECT * FROM attendance WHERE student_id = 123");

// CORRECT (Include school_id)
$stmt = $db->prepare("SELECT * FROM attendance WHERE student_id = ? AND school_id = ?");
```

### Issue: Fee payments showing for wrong student
**Cause**: Incorrect JOIN
```php
// WRONG
SELECT * FROM fee_payments;

// CORRECT
SELECT fp.* FROM fee_payments fp
WHERE fp.student_id = ? AND fp.school_id = ?;
```

---

## 4. NEW PATTERN: Repository Usage

```php
// In API file (students.php)
require_once __DIR__ . '/../repositories/StudentRepository.php';

$repo = new StudentRepository(getDB());

// Get student with all related data
$student = $repo->findWithDetails($studentId);
// Returns: student + parents + medical + documents

// Get attendance (from attendance table, NOT student table)
$attendance = $repo->getAttendance($studentId);
```

---

## 5. DATABASE TABLE PREFIXES

| Prefix | Module | Example Tables |
|--------|--------|----------------|
| student_ | Students | student_parents, student_medical |
| teacher_ | Teachers | teacher_qualifications, teacher_absences |
| fee_ | Fees | fee_structures, fee_payments |
| exam_ | Exams | exam_types, exam_marks |
| transport_ | Transport | transport_routes, transport_stops |
| hostel_ | Hostel | hostel_blocks, hostel_rooms |
| library_ | Library | library_books, library_issues |

---

## 6. VALIDATION BEFORE DEPLOY

- [ ] All domain tables have school_id column
- [ ] All queries filter by school_id
- [ ] No mock data in production
- [ ] All foreign keys have indexes
- [ ] Activity logging enabled
- [ ] RBAC permissions enforced

---

## 7. DOCUMENTATION FILES

1. **ARCHITECTURE_PROPOSAL.md** - Detailed architecture documentation
2. **architecture_diagram.html** - Visual diagrams (open in browser)
3. **QUICK_REFERENCE.md** - This file

---

## 8. TEAM CONTACTS

For architecture questions, refer to the documentation files in `/docs/` folder.
