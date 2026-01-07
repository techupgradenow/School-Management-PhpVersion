-- ============================================
-- Timetable Management System Schema
-- MySQL Version for EduManage Pro
-- ============================================
-- Supports:
-- - Auto timetable generation
-- - Teacher availability & multi-subject teaching
-- - Break/Lunch/Assembly fixed slots
-- - Subject weekly requirements
-- - Lab double-period rules
-- - Max continuous periods, max per day limits
-- - Teacher absence & auto substitution
-- - Swap/exchange requests
-- ============================================

USE edumanage_pro;

-- ============================================
-- 1) ACADEMIC YEAR TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS academic_years (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,                    -- "2025-2026"
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_academic_year_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2) TIMETABLE SUBJECTS (extended from existing subjects)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_subjects (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,                    -- "MATH"
    name VARCHAR(100) NOT NULL,                   -- "Mathematics"
    short_name VARCHAR(10),                       -- "Math"
    is_lab TINYINT(1) NOT NULL DEFAULT 0,
    requires_double_period TINYINT(1) NOT NULL DEFAULT 0,
    color VARCHAR(7) DEFAULT '#3498db',           -- For UI display
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_subject_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3) TEACHER-SUBJECT MAPPING (multi-subject support)
-- ============================================
CREATE TABLE IF NOT EXISTS teacher_subjects (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL,              -- References teachers.id
    subject_id BIGINT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,              -- Primary subject for this teacher
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_teacher_subject (teacher_id, subject_id),
    KEY idx_teacher_id (teacher_id),
    KEY idx_subject_id (subject_id),
    CONSTRAINT fk_ts_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4) TIMETABLE SLOTS (days + periods structure)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_slots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,                 -- 1=Mon, 2=Tue, ... 7=Sun
    period_no TINYINT NOT NULL,                   -- 1, 2, 3, ... 20
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_type ENUM('CLASS', 'BREAK', 'LUNCH', 'ASSEMBLY', 'FREE') NOT NULL DEFAULT 'CLASS',
    slot_label VARCHAR(50),                       -- "Period 1", "Lunch Break"
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_day_period (day_of_week, period_no),
    KEY idx_slot_type (slot_type),
    CONSTRAINT chk_day CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT chk_period CHECK (period_no BETWEEN 1 AND 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5) TEACHER UNAVAILABILITY (availability calendar)
-- ============================================
CREATE TABLE IF NOT EXISTS teacher_unavailability (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL,
    slot_id BIGINT NOT NULL,
    reason VARCHAR(255),
    is_recurring TINYINT(1) DEFAULT 1,            -- Weekly recurring or one-time
    effective_date DATE,                          -- For non-recurring
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_teacher_slot (teacher_id, slot_id),
    KEY idx_teacher_unavail (teacher_id),
    CONSTRAINT fk_tu_slot FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6) CLASS SUBJECT REQUIREMENTS (periods per week)
-- ============================================
CREATE TABLE IF NOT EXISTS class_subject_requirements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT NOT NULL,
    class_id VARCHAR(50) NOT NULL,                -- "10" or "10-A"
    section_id VARCHAR(10),                       -- "A", "B", etc.
    subject_id BIGINT NOT NULL,
    periods_per_week TINYINT NOT NULL DEFAULT 0,
    preferred_teacher_id VARCHAR(50),             -- Preferred teacher for this class-subject
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_class_subject (academic_year_id, class_id, section_id, subject_id),
    KEY idx_class (class_id, section_id),
    CONSTRAINT fk_csr_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_csr_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE CASCADE,
    CONSTRAINT chk_periods CHECK (periods_per_week >= 0 AND periods_per_week <= 20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7) ROOMS (for lab/room constraints)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_rooms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL,                    -- "LAB1", "CR-101"
    name VARCHAR(100) NOT NULL,
    room_type ENUM('CLASSROOM', 'LAB', 'AUDITORIUM', 'LIBRARY', 'SPORTS') NOT NULL DEFAULT 'CLASSROOM',
    capacity INT DEFAULT 40,
    building VARCHAR(50),
    floor_no VARCHAR(10),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_room_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8) SUBJECT-ROOM TYPE MAPPING
-- ============================================
CREATE TABLE IF NOT EXISTS subject_room_requirements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    subject_id BIGINT NOT NULL,
    room_type ENUM('CLASSROOM', 'LAB', 'AUDITORIUM', 'LIBRARY', 'SPORTS') NOT NULL,
    is_mandatory TINYINT(1) DEFAULT 0,
    UNIQUE KEY uk_subject_room (subject_id, room_type),
    CONSTRAINT fk_srr_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9) TIMETABLE RULES (configurable rules engine)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_rules (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,                   -- "PT last period only"
    description TEXT,
    rule_type ENUM('HARD', 'SOFT') NOT NULL,      -- HARD = must satisfy, SOFT = optimize
    category ENUM('SUBJECT_TIME', 'TEACHER_LOAD', 'LAB', 'FIXED', 'ABSENCE', 'SWAP', 'ROOM', 'GENERAL') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 100,            -- Lower = more important
    weight INT NOT NULL DEFAULT 1,                -- For SOFT rule penalties
    params JSON NOT NULL,                         -- Rule parameters as JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rule_type (rule_type),
    KEY idx_category (category),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10) FIXED SLOT ASSIGNMENTS (assembly, events, locked)
-- ============================================
CREATE TABLE IF NOT EXISTS fixed_slot_assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT NOT NULL,
    class_id VARCHAR(50),                         -- NULL = whole school event
    section_id VARCHAR(10),
    slot_id BIGINT NOT NULL,
    subject_id BIGINT,
    teacher_id VARCHAR(50),
    room_id BIGINT,
    title VARCHAR(100),                           -- "Assembly", "Club Hour"
    assignment_type ENUM('FIXED', 'LOCKED', 'EVENT') NOT NULL DEFAULT 'FIXED',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fixed_slot (academic_year_id, class_id, section_id, slot_id),
    KEY idx_slot (slot_id),
    CONSTRAINT fk_fsa_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_fsa_slot FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_fsa_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_fsa_room FOREIGN KEY (room_id) REFERENCES timetable_rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11) TIMETABLE RUNS (versioning/history)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT NOT NULL,
    run_type ENUM('FULL', 'REPAIR', 'SWAP', 'ABSENCE', 'MANUAL') NOT NULL DEFAULT 'FULL',
    status ENUM('PENDING', 'RUNNING', 'COMPLETED', 'FAILED', 'PUBLISHED') NOT NULL DEFAULT 'PENDING',
    is_active TINYINT(1) NOT NULL DEFAULT 0,      -- Only one active run per year
    total_classes INT DEFAULT 0,
    total_periods INT DEFAULT 0,
    conflicts_count INT DEFAULT 0,
    score DECIMAL(10,2),                          -- Optimization score
    notes TEXT,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    KEY idx_year (academic_year_id),
    KEY idx_status (status),
    CONSTRAINT fk_tr_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12) TIMETABLE ENTRIES (the actual timetable)
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    timetable_run_id BIGINT NOT NULL,
    class_id VARCHAR(50) NOT NULL,
    section_id VARCHAR(10),
    slot_id BIGINT NOT NULL,
    subject_id BIGINT,
    teacher_id VARCHAR(50),
    room_id BIGINT,
    status ENUM('SCHEDULED', 'FREE', 'STUDY_HOUR', 'SUBSTITUTED', 'CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    original_teacher_id VARCHAR(50),              -- For substitutions
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_entry (timetable_run_id, class_id, section_id, slot_id),
    KEY idx_teacher_slot (teacher_id, slot_id),
    KEY idx_class_slot (class_id, section_id, slot_id),
    KEY idx_room_slot (room_id, slot_id),
    CONSTRAINT fk_te_run FOREIGN KEY (timetable_run_id) REFERENCES timetable_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_te_slot FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_te_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE SET NULL,
    CONSTRAINT fk_te_room FOREIGN KEY (room_id) REFERENCES timetable_rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13) TEACHER ABSENCES
-- ============================================
CREATE TABLE IF NOT EXISTS teacher_absences (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    absence_type ENUM('LEAVE', 'SICK', 'TRAINING', 'PERSONAL', 'OTHER') NOT NULL DEFAULT 'LEAVE',
    reason TEXT,
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    approved_by VARCHAR(50),
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_teacher (teacher_id),
    KEY idx_dates (start_date, end_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14) SUBSTITUTION RECORDS
-- ============================================
CREATE TABLE IF NOT EXISTS substitution_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    absence_id BIGINT,
    timetable_entry_id BIGINT NOT NULL,
    original_teacher_id VARCHAR(50) NOT NULL,
    substitute_teacher_id VARCHAR(50),
    substitution_date DATE NOT NULL,
    slot_id BIGINT NOT NULL,
    class_id VARCHAR(50) NOT NULL,
    section_id VARCHAR(10),
    subject_id BIGINT,
    status ENUM('PENDING', 'ASSIGNED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    assignment_type ENUM('AUTO', 'MANUAL', 'STUDY_HOUR', 'FREE') NOT NULL DEFAULT 'AUTO',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_date (substitution_date),
    KEY idx_original_teacher (original_teacher_id),
    KEY idx_substitute (substitute_teacher_id),
    CONSTRAINT fk_sr_absence FOREIGN KEY (absence_id) REFERENCES teacher_absences(id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_entry FOREIGN KEY (timetable_entry_id) REFERENCES timetable_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_sr_slot FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_sr_subject FOREIGN KEY (subject_id) REFERENCES timetable_subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15) PERIOD SWAP REQUESTS
-- ============================================
CREATE TABLE IF NOT EXISTS period_swap_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT NOT NULL,
    requested_by_teacher_id VARCHAR(50) NOT NULL,
    swap_with_teacher_id VARCHAR(50),             -- NULL if swapping own periods
    class_id VARCHAR(50) NOT NULL,
    section_id VARCHAR(10),
    slot_a_id BIGINT NOT NULL,                    -- First slot to swap
    slot_b_id BIGINT NOT NULL,                    -- Second slot to swap
    swap_date DATE,                               -- Specific date or NULL for recurring
    status ENUM('PENDING', 'APPROVED', 'REJECTED', 'APPLIED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    reason TEXT,
    reviewed_by VARCHAR(50),
    reviewed_at TIMESTAMP NULL,
    applied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_requester (requested_by_teacher_id),
    KEY idx_status (status),
    CONSTRAINT fk_psr_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_psr_slot_a FOREIGN KEY (slot_a_id) REFERENCES timetable_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_psr_slot_b FOREIGN KEY (slot_b_id) REFERENCES timetable_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16) TIMETABLE CONFLICTS LOG
-- ============================================
CREATE TABLE IF NOT EXISTS timetable_conflicts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    timetable_run_id BIGINT NOT NULL,
    conflict_type ENUM('TEACHER_CLASH', 'ROOM_CLASH', 'REQUIREMENT_UNMET', 'RULE_VIOLATION', 'AVAILABILITY') NOT NULL,
    severity ENUM('ERROR', 'WARNING', 'INFO') NOT NULL DEFAULT 'WARNING',
    class_id VARCHAR(50),
    section_id VARCHAR(10),
    slot_id BIGINT,
    teacher_id VARCHAR(50),
    subject_id BIGINT,
    room_id BIGINT,
    rule_id BIGINT,
    description TEXT NOT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_run (timetable_run_id),
    KEY idx_type (conflict_type),
    KEY idx_severity (severity),
    CONSTRAINT fk_tc_run FOREIGN KEY (timetable_run_id) REFERENCES timetable_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default Academic Year
INSERT INTO academic_years (name, start_date, end_date, is_active) VALUES
('2024-2025', '2024-04-01', '2025-03-31', 1)
ON DUPLICATE KEY UPDATE is_active = 1;

-- Default Timetable Slots (Monday to Saturday, 8 periods + breaks)
INSERT INTO timetable_slots (day_of_week, period_no, start_time, end_time, slot_type, slot_label) VALUES
-- Monday
(1, 1, '08:00:00', '08:45:00', 'ASSEMBLY', 'Assembly'),
(1, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 1'),
(1, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 2'),
(1, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(1, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 3'),
(1, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 4'),
(1, 7, '12:00:00', '12:45:00', 'LUNCH', 'Lunch Break'),
(1, 8, '12:45:00', '13:30:00', 'CLASS', 'Period 5'),
(1, 9, '13:30:00', '14:15:00', 'CLASS', 'Period 6'),
(1, 10, '14:15:00', '15:00:00', 'CLASS', 'Period 7'),
-- Tuesday
(2, 1, '08:00:00', '08:45:00', 'CLASS', 'Period 1'),
(2, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 2'),
(2, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 3'),
(2, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(2, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 4'),
(2, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 5'),
(2, 7, '12:00:00', '12:45:00', 'LUNCH', 'Lunch Break'),
(2, 8, '12:45:00', '13:30:00', 'CLASS', 'Period 6'),
(2, 9, '13:30:00', '14:15:00', 'CLASS', 'Period 7'),
(2, 10, '14:15:00', '15:00:00', 'CLASS', 'Period 8'),
-- Wednesday
(3, 1, '08:00:00', '08:45:00', 'CLASS', 'Period 1'),
(3, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 2'),
(3, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 3'),
(3, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(3, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 4'),
(3, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 5'),
(3, 7, '12:00:00', '12:45:00', 'LUNCH', 'Lunch Break'),
(3, 8, '12:45:00', '13:30:00', 'CLASS', 'Period 6'),
(3, 9, '13:30:00', '14:15:00', 'CLASS', 'Period 7'),
(3, 10, '14:15:00', '15:00:00', 'CLASS', 'Period 8'),
-- Thursday
(4, 1, '08:00:00', '08:45:00', 'CLASS', 'Period 1'),
(4, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 2'),
(4, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 3'),
(4, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(4, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 4'),
(4, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 5'),
(4, 7, '12:00:00', '12:45:00', 'LUNCH', 'Lunch Break'),
(4, 8, '12:45:00', '13:30:00', 'CLASS', 'Period 6'),
(4, 9, '13:30:00', '14:15:00', 'CLASS', 'Period 7'),
(4, 10, '14:15:00', '15:00:00', 'CLASS', 'Period 8'),
-- Friday
(5, 1, '08:00:00', '08:45:00', 'CLASS', 'Period 1'),
(5, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 2'),
(5, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 3'),
(5, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(5, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 4'),
(5, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 5'),
(5, 7, '12:00:00', '12:45:00', 'LUNCH', 'Lunch Break'),
(5, 8, '12:45:00', '13:30:00', 'CLASS', 'Period 6'),
(5, 9, '13:30:00', '14:15:00', 'CLASS', 'Period 7'),
(5, 10, '14:15:00', '15:00:00', 'CLASS', 'Period 8'),
-- Saturday (half day)
(6, 1, '08:00:00', '08:45:00', 'CLASS', 'Period 1'),
(6, 2, '08:45:00', '09:30:00', 'CLASS', 'Period 2'),
(6, 3, '09:30:00', '10:15:00', 'CLASS', 'Period 3'),
(6, 4, '10:15:00', '10:30:00', 'BREAK', 'Short Break'),
(6, 5, '10:30:00', '11:15:00', 'CLASS', 'Period 4'),
(6, 6, '11:15:00', '12:00:00', 'CLASS', 'Period 5')
ON DUPLICATE KEY UPDATE slot_label = VALUES(slot_label);

-- Default Subjects
INSERT INTO timetable_subjects (code, name, short_name, is_lab, requires_double_period, color) VALUES
('ENG', 'English', 'Eng', 0, 0, '#3498db'),
('MATH', 'Mathematics', 'Math', 0, 0, '#e74c3c'),
('SCI', 'Science', 'Sci', 0, 0, '#2ecc71'),
('SST', 'Social Studies', 'SST', 0, 0, '#f39c12'),
('HINDI', 'Hindi', 'Hin', 0, 0, '#9b59b6'),
('PHY', 'Physics', 'Phy', 0, 0, '#1abc9c'),
('CHEM', 'Chemistry', 'Chem', 0, 0, '#e67e22'),
('BIO', 'Biology', 'Bio', 0, 0, '#27ae60'),
('COMP', 'Computer Science', 'Comp', 1, 1, '#34495e'),
('PT', 'Physical Training', 'PT', 0, 0, '#16a085'),
('ART', 'Art & Craft', 'Art', 0, 0, '#8e44ad'),
('MUSIC', 'Music', 'Mus', 0, 0, '#d35400')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Default Rooms
INSERT INTO timetable_rooms (code, name, room_type, capacity) VALUES
('CR-101', 'Classroom 101', 'CLASSROOM', 40),
('CR-102', 'Classroom 102', 'CLASSROOM', 40),
('CR-103', 'Classroom 103', 'CLASSROOM', 40),
('CR-104', 'Classroom 104', 'CLASSROOM', 40),
('CR-105', 'Classroom 105', 'CLASSROOM', 40),
('LAB-CS', 'Computer Lab', 'LAB', 30),
('LAB-PHY', 'Physics Lab', 'LAB', 30),
('LAB-CHEM', 'Chemistry Lab', 'LAB', 30),
('LAB-BIO', 'Biology Lab', 'LAB', 30),
('AUD-1', 'Main Auditorium', 'AUDITORIUM', 500),
('LIB', 'Library', 'LIBRARY', 100),
('GROUND', 'Sports Ground', 'SPORTS', 200)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Default Rules
INSERT INTO timetable_rules (name, description, rule_type, category, priority, weight, params) VALUES
('No Teacher Double Booking', 'Teacher cannot teach two classes at the same time', 'HARD', 'GENERAL', 1, 1, '{"scope": "teacher", "constraint": "unique_per_slot"}'),
('No Class Double Booking', 'Class cannot have two subjects at the same time', 'HARD', 'GENERAL', 1, 1, '{"scope": "class", "constraint": "unique_per_slot"}'),
('Teacher Must Be Available', 'Teacher must not be marked unavailable for the slot', 'HARD', 'GENERAL', 2, 1, '{"check": "availability"}'),
('Teacher Must Be Qualified', 'Teacher must be assigned to teach the subject', 'HARD', 'GENERAL', 3, 1, '{"check": "qualification"}'),
('Meet Weekly Requirements', 'Each class-subject must meet required periods per week', 'HARD', 'GENERAL', 5, 1, '{"check": "requirements"}'),
('No Teaching During Breaks', 'No classes during break/lunch slots', 'HARD', 'FIXED', 1, 1, '{"slot_types": ["BREAK", "LUNCH"]}'),
('Lab Double Period', 'Lab subjects require consecutive double periods', 'HARD', 'LAB', 10, 1, '{"requires_consecutive": true, "period_count": 2}'),
('PT Last Period Preferred', 'Physical Training should be scheduled in last periods', 'SOFT', 'SUBJECT_TIME', 50, 5, '{"subject_code": "PT", "preferred_periods": [9, 10]}'),
('Core Subjects Morning', 'Math, Science, English preferred in morning slots', 'SOFT', 'SUBJECT_TIME', 60, 3, '{"subject_codes": ["MATH", "SCI", "ENG"], "preferred_periods": [1, 2, 3, 4, 5]}'),
('Teacher Max 6 Continuous', 'Teacher should not teach more than 6 continuous periods', 'SOFT', 'TEACHER_LOAD', 70, 4, '{"max_continuous": 6}'),
('Teacher Max 8 Per Day', 'Teacher should not teach more than 8 periods per day', 'SOFT', 'TEACHER_LOAD', 75, 3, '{"max_per_day": 8}'),
('Minimize Teacher Gaps', 'Minimize free periods between teaching periods for teachers', 'SOFT', 'TEACHER_LOAD', 80, 2, '{"minimize": "gaps"}'),
('Spread Subjects Across Week', 'Avoid same subject multiple times in a day', 'SOFT', 'SUBJECT_TIME', 85, 2, '{"max_per_day": 2}'),
('Balance Teacher Load', 'Distribute teaching load evenly across weekdays', 'SOFT', 'TEACHER_LOAD', 90, 1, '{"balance": "weekly"}');

-- Subject Room Requirements
INSERT INTO subject_room_requirements (subject_id, room_type, is_mandatory)
SELECT id, 'LAB', 1 FROM timetable_subjects WHERE code = 'COMP'
ON DUPLICATE KEY UPDATE is_mandatory = 1;

INSERT INTO subject_room_requirements (subject_id, room_type, is_mandatory)
SELECT id, 'SPORTS', 1 FROM timetable_subjects WHERE code = 'PT'
ON DUPLICATE KEY UPDATE is_mandatory = 1;

SELECT 'Timetable schema created successfully!' AS status;
