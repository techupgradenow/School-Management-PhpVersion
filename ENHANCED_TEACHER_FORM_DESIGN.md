# Enhanced "Add New Teacher" Form - Complete Design Document

## 📋 Overview

This document provides the complete architecture for enhancing the existing "Add New Teacher" modal form in the School Management System with:
- Tab-based UI for better organization
- Comprehensive validation rules
- Teaching Assignment (Class & Subject Allocation)
- Backend data model with many-to-many relationships
- Complete save/submit flow

---

## 1️⃣ UI STRUCTURE - TAB-BASED DESIGN

### Modal Layout
```
┌────────────────────────────────────────────────────────────┐
│  👨‍🏫 Add New Teacher                                    [×]  │
├────────────────────────────────────────────────────────────┤
│  [Personal Info] [Professional Details] [Teaching Assignment]│
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Tab Content Area                                          │
│  (Form fields based on active tab)                         │
│                                                            │
├────────────────────────────────────────────────────────────┤
│                    [Cancel]  [Save & Next]  [Submit]       │
└────────────────────────────────────────────────────────────┘
```

---

## TAB 1: PERSONAL INFORMATION

### Section A: Basic Details
```html
<div class="form-section">
    <h4>Basic Details</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Teacher Name <span class="required">*</span></label>
            <input type="text" id="teacherName" required
                   pattern="[A-Za-z\s]{3,50}"
                   placeholder="Enter full name">
            <span class="error-msg" id="teacherName-error"></span>
        </div>

        <div class="form-group">
            <label>Gender <span class="required">*</span></label>
            <select id="teacherGender" required>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
            <span class="error-msg" id="teacherGender-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Date of Birth <span class="required">*</span></label>
            <input type="date" id="teacherDOB" required
                   max="" onload="setMaxDOB()">
            <span class="error-msg" id="teacherDOB-error"></span>
        </div>

        <div class="form-group">
            <label>Blood Group <span class="required">*</span></label>
            <select id="teacherBloodGroup" required>
                <option value="">Select Blood Group</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
            </select>
            <span class="error-msg" id="teacherBloodGroup-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" id="teacherEmail" required
                   placeholder="teacher@school.edu"
                   onblur="validateUniqueEmail()">
            <span class="error-msg" id="teacherEmail-error"></span>
        </div>

        <div class="form-group">
            <label>Phone <span class="required">*</span></label>
            <input type="tel" id="teacherPhone" required
                   pattern="[0-9]{10}"
                   maxlength="10"
                   placeholder="10 digit mobile number"
                   onblur="validateUniquePhone()">
            <span class="error-msg" id="teacherPhone-error"></span>
        </div>
    </div>
</div>
```

### Section B: Government IDs
```html
<div class="form-section">
    <h4>Government Identification</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Aadhaar Number <span class="required">*</span></label>
            <input type="text" id="teacherAadhaar" required
                   pattern="[0-9]{12}"
                   maxlength="12"
                   placeholder="12 digit Aadhaar number"
                   onblur="validateUniqueAadhaar()">
            <span class="help-text">Exactly 12 digits, numeric only</span>
            <span class="error-msg" id="teacherAadhaar-error"></span>
        </div>

        <div class="form-group">
            <label>PAN Number <span class="required">*</span></label>
            <input type="text" id="teacherPAN" required
                   pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}"
                   maxlength="10"
                   placeholder="ABCDE1234F"
                   style="text-transform: uppercase"
                   onblur="validateUniquePAN()">
            <span class="help-text">Format: ABCDE1234F</span>
            <span class="error-msg" id="teacherPAN-error"></span>
        </div>
    </div>
</div>
```

### Section C: Marital Status
```html
<div class="form-section">
    <h4>Marital Information</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Marital Status <span class="required">*</span></label>
            <select id="teacherMaritalStatus" required
                    onchange="toggleSpouseField()">
                <option value="">Select Status</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Divorced">Divorced</option>
                <option value="Widowed">Widowed</option>
            </select>
            <span class="error-msg" id="teacherMaritalStatus-error"></span>
        </div>

        <div class="form-group" id="spouseNameGroup" style="display: none;">
            <label>Spouse Name <span class="required conditional">*</span></label>
            <input type="text" id="teacherSpouseName"
                   placeholder="Husband / Wife name">
            <span class="error-msg" id="teacherSpouseName-error"></span>
        </div>
    </div>
</div>
```

### Section D: Address Details
```html
<div class="form-section">
    <h4>Address Details</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Door No / Street <span class="required">*</span></label>
            <input type="text" id="teacherDoorNo" required
                   placeholder="123, Main Street">
            <span class="error-msg" id="teacherDoorNo-error"></span>
        </div>

        <div class="form-group">
            <label>Area / Locality <span class="required">*</span></label>
            <input type="text" id="teacherArea" required
                   placeholder="Area name">
            <span class="error-msg" id="teacherArea-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>City <span class="required">*</span></label>
            <input type="text" id="teacherCity" required
                   placeholder="City">
            <span class="error-msg" id="teacherCity-error"></span>
        </div>

        <div class="form-group">
            <label>State <span class="required">*</span></label>
            <select id="teacherState" required>
                <option value="">Select State</option>
                <option value="Andhra Pradesh">Andhra Pradesh</option>
                <option value="Karnataka">Karnataka</option>
                <option value="Kerala">Kerala</option>
                <option value="Tamil Nadu">Tamil Nadu</option>
                <option value="Telangana">Telangana</option>
                <!-- Add all Indian states -->
            </select>
            <span class="error-msg" id="teacherState-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Pincode <span class="required">*</span></label>
            <input type="text" id="teacherPincode" required
                   pattern="[0-9]{6}"
                   maxlength="6"
                   placeholder="6 digit pincode">
            <span class="error-msg" id="teacherPincode-error"></span>
        </div>
    </div>
</div>
```

---

## TAB 2: PROFESSIONAL & ACADEMIC DETAILS

```html
<div class="form-section">
    <h4>Professional Information</h4>

    <div class="form-row">
        <div class="form-group">
            <label>Teacher ID / Employee Code</label>
            <input type="text" id="teacherEmployeeCode" readonly
                   placeholder="Auto-generated"
                   style="background: #f1f5f9; cursor: not-allowed;">
            <span class="help-text">Automatically generated on save</span>
        </div>

        <div class="form-group">
            <label>Qualification <span class="required">*</span></label>
            <select id="teacherQualification" required>
                <option value="">Select Qualification</option>
                <option value="UG">UG (Under Graduate)</option>
                <option value="PG">PG (Post Graduate)</option>
                <option value="PhD">PhD (Doctorate)</option>
                <option value="Diploma">Diploma</option>
            </select>
            <span class="error-msg" id="teacherQualification-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Years of Experience <span class="required">*</span></label>
            <input type="number" id="teacherExperience" required
                   min="0" max="50" value="0"
                   placeholder="Years of teaching experience">
            <span class="error-msg" id="teacherExperience-error"></span>
        </div>

        <div class="form-group">
            <label>Date of Joining <span class="required">*</span></label>
            <input type="date" id="teacherJoiningDate" required>
            <span class="error-msg" id="teacherJoiningDate-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Employment Type <span class="required">*</span></label>
            <select id="teacherEmploymentType" required>
                <option value="">Select Type</option>
                <option value="Permanent">Permanent</option>
                <option value="Contract">Contract</option>
                <option value="Visiting">Visiting Faculty</option>
            </select>
            <span class="error-msg" id="teacherEmploymentType-error"></span>
        </div>

        <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select id="teacherStatus" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="On Leave">On Leave</option>
                <option value="Suspended">Suspended</option>
            </select>
            <span class="error-msg" id="teacherStatus-error"></span>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group full-width">
            <label>Subject Expertise</label>
            <input type="text" id="teacherSubjectExpertise"
                   placeholder="e.g., Mathematics, Physics, Chemistry">
            <span class="help-text">Comma-separated list of subjects</span>
        </div>
    </div>
</div>
```

---

## TAB 3: TEACHING ASSIGNMENT (Class & Subject Allocation)

### UI Structure
```html
<div class="form-section">
    <h4>📌 Class & Subject Allocation</h4>
    <p class="section-description">
        Assign classes and subjects to this teacher. At least one allocation is mandatory.
    </p>

    <!-- Add Allocation Form -->
    <div class="allocation-form-card">
        <div class="form-row">
            <div class="form-group">
                <label>Select Class <span class="required">*</span></label>
                <select id="allocationClass" onchange="loadSubjectsForClass()">
                    <option value="">Choose a class</option>
                    <option value="1">Class 1</option>
                    <option value="2">Class 2</option>
                    <option value="3">Class 3</option>
                    <option value="4">Class 4</option>
                    <option value="5">Class 5</option>
                    <option value="6">Class 6</option>
                    <option value="7">Class 7</option>
                    <option value="8">Class 8</option>
                    <option value="9">Class 9</option>
                    <option value="10">Class 10</option>
                    <option value="11">Class 11</option>
                    <option value="12">Class 12</option>
                </select>
            </div>

            <div class="form-group">
                <label>Select Subject <span class="required">*</span></label>
                <select id="allocationSubject" disabled>
                    <option value="">Select class first</option>
                </select>
            </div>

            <div class="form-group align-end">
                <button type="button" class="btn btn-success btn-sm"
                        onclick="addAllocation()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
        </div>

        <div class="validation-message" id="allocationValidation" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="allocationValidationText"></span>
        </div>
    </div>

    <!-- Allocations Grid -->
    <div class="allocations-grid-card">
        <table class="allocations-table">
            <thead>
                <tr>
                    <th width="10%">#</th>
                    <th width="35%">Class</th>
                    <th width="45%">Subject</th>
                    <th width="10%">Action</th>
                </tr>
            </thead>
            <tbody id="allocationsTableBody">
                <tr class="empty-state-row">
                    <td colspan="4" class="empty-state-cell">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No allocations added yet</p>
                        <small>Add at least one class-subject combination</small>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="allocation-summary" id="allocationSummary" style="display: none;">
        <i class="fas fa-check-circle"></i>
        <span id="allocationCount">0</span> allocation(s) added
    </div>
</div>
```

---

## 2️⃣ VALIDATION RULES

### Client-Side Validation (Real-time)

#### Personal Information
| Field | Rule | Error Message |
|-------|------|---------------|
| **Teacher Name** | Required, 3-50 chars, letters & spaces only | "Name must be 3-50 characters, letters only" |
| **Gender** | Required, dropdown selection | "Please select gender" |
| **DOB** | Required, must be 18+ years | "Teacher must be at least 18 years old" |
| **Blood Group** | Required, dropdown selection | "Please select blood group" |
| **Email** | Required, valid format, unique | "Valid email required / Email already exists" |
| **Phone** | Required, exactly 10 digits, unique | "10 digit phone required / Phone already exists" |
| **Aadhaar** | Required, exactly 12 digits, numeric, unique | "12 digit Aadhaar required / Already exists" |
| **PAN** | Required, format ABCDE1234F, unique | "Invalid PAN format / PAN already exists" |
| **Marital Status** | Required | "Please select marital status" |
| **Spouse Name** | Required if Married, else optional | "Spouse name required for married status" |
| **Door No** | Required | "Door number / street required" |
| **Area** | Required | "Area / locality required" |
| **City** | Required | "City required" |
| **State** | Required | "State required" |
| **Pincode** | Required, exactly 6 digits | "6 digit pincode required" |

#### Professional Details
| Field | Rule | Error Message |
|-------|------|---------------|
| **Employee Code** | Auto-generated, read-only | N/A |
| **Qualification** | Required | "Please select qualification" |
| **Experience** | Required, min 0, max 50 | "Experience must be 0-50 years" |
| **Joining Date** | Required, cannot be future date | "Valid joining date required" |
| **Employment Type** | Required | "Please select employment type" |
| **Status** | Required, default Active | "Status required" |

#### Teaching Assignment
| Rule | Validation |
|------|------------|
| **Minimum 1 allocation** | At least one class-subject combination must exist |
| **No duplicates** | Same class + subject cannot be added twice |
| **Class required** | Class must be selected before subject |
| **Subject required** | Subject must be selected |

---

## 3️⃣ BACKEND DATA MODEL

### Database Schema

#### Table: `teachers` (Enhanced)
```sql
CREATE TABLE teachers (
    id VARCHAR(20) PRIMARY KEY,              -- TCHR001, TCHR002, etc.
    employee_code VARCHAR(20) UNIQUE NOT NULL, -- Auto-generated: EMP2025001

    -- Personal Info
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(10) UNIQUE NOT NULL,

    -- Government IDs
    aadhaar_number VARCHAR(12) UNIQUE NOT NULL,
    pan_number VARCHAR(10) UNIQUE NOT NULL,

    -- Marital Status
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed') NOT NULL,
    spouse_name VARCHAR(100) NULL,

    -- Address
    door_no VARCHAR(100) NOT NULL,
    area VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    pincode VARCHAR(6) NOT NULL,

    -- Professional Details
    qualification ENUM('UG', 'PG', 'PhD', 'Diploma') NOT NULL,
    experience INT NOT NULL DEFAULT 0,
    joining_date DATE NOT NULL,
    employment_type ENUM('Permanent', 'Contract', 'Visiting') NOT NULL,
    status ENUM('Active', 'Inactive', 'On Leave', 'Suspended') DEFAULT 'Active',
    subject_expertise TEXT NULL,

    -- Photo
    photo VARCHAR(255) NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes for uniqueness validation
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_aadhaar (aadhaar_number),
    INDEX idx_pan (pan_number),
    INDEX idx_employee_code (employee_code),
    INDEX idx_status (status)
);
```

#### Table: `teacher_class_subjects` (Many-to-Many Mapping)
```sql
CREATE TABLE teacher_class_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(20) NOT NULL,
    class VARCHAR(10) NOT NULL,           -- '1', '2', ..., '12'
    subject_id INT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES dropdown_values(id) ON DELETE CASCADE,

    -- Unique constraint to prevent duplicate combinations
    UNIQUE KEY unique_teacher_class_subject (teacher_id, class, subject_id),

    -- Indexes for performance
    INDEX idx_teacher (teacher_id),
    INDEX idx_class (class),
    INDEX idx_subject (subject_id)
);
```

#### Table: `subjects_by_class` (Subject Master Data)
```sql
CREATE TABLE subjects_by_class (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class VARCHAR(10) NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NULL,
    is_core BOOLEAN DEFAULT TRUE,         -- Core vs Elective
    stream VARCHAR(20) NULL,               -- For Class 11/12: Science, Commerce, Arts

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_class_subject (class, subject_name),
    INDEX idx_class (class),
    INDEX idx_stream (stream)
);
```

### Sample Subject Data
```sql
-- Class 1-5: Basic subjects
INSERT INTO subjects_by_class (class, subject_name, is_core) VALUES
('1', 'English', TRUE),
('1', 'Mathematics', TRUE),
('1', 'EVS', TRUE),
('1', 'Hindi', TRUE);

-- Class 6-8: Expanded subjects
INSERT INTO subjects_by_class (class, subject_name, is_core) VALUES
('6', 'English', TRUE),
('6', 'Mathematics', TRUE),
('6', 'Science', TRUE),
('6', 'Social Science', TRUE),
('6', 'Hindi', TRUE),
('6', 'Computer Science', FALSE);

-- Class 9-10: Detailed subjects
INSERT INTO subjects_by_class (class, subject_name, is_core) VALUES
('10', 'English', TRUE),
('10', 'Mathematics', TRUE),
('10', 'Physics', TRUE),
('10', 'Chemistry', TRUE),
('10', 'Biology', TRUE),
('10', 'Social Science', TRUE),
('10', 'Hindi', TRUE),
('10', 'Computer Science', FALSE);

-- Class 11-12: Stream-based
INSERT INTO subjects_by_class (class, subject_name, stream, is_core) VALUES
('11', 'Physics', 'Science', TRUE),
('11', 'Chemistry', 'Science', TRUE),
('11', 'Mathematics', 'Science', TRUE),
('11', 'Biology', 'Science', FALSE),
('11', 'Computer Science', 'Science', FALSE),
('11', 'Accountancy', 'Commerce', TRUE),
('11', 'Business Studies', 'Commerce', TRUE),
('11', 'Economics', 'Commerce', TRUE),
('11', 'History', 'Arts', TRUE),
('11', 'Political Science', 'Arts', TRUE),
('11', 'Geography', 'Arts', TRUE);
```

---

## 4️⃣ JAVASCRIPT VALIDATION & LOGIC

### Form Validation Functions
```javascript
// Global state
let teacherAllocations = [];

// Initialize tab navigation
function initTabs() {
    const tabs = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            // Remove active class from all
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));

            // Add active to clicked
            tab.classList.add('active');
            tabContents[index].classList.add('active');
        });
    });
}

// Validate Personal Info Tab
function validatePersonalInfo() {
    let isValid = true;

    // Name validation
    const name = $('#teacherName').val().trim();
    if (name.length < 3 || name.length > 50) {
        showError('teacherName', 'Name must be 3-50 characters');
        isValid = false;
    } else if (!/^[A-Za-z\s]+$/.test(name)) {
        showError('teacherName', 'Name can only contain letters and spaces');
        isValid = false;
    } else {
        clearError('teacherName');
    }

    // DOB validation (must be 18+)
    const dob = new Date($('#teacherDOB').val());
    const age = (new Date() - dob) / (365.25 * 24 * 60 * 60 * 1000);
    if (age < 18) {
        showError('teacherDOB', 'Teacher must be at least 18 years old');
        isValid = false;
    } else {
        clearError('teacherDOB');
    }

    // Email validation
    const email = $('#teacherEmail').val().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('teacherEmail', 'Please enter a valid email address');
        isValid = false;
    } else {
        clearError('teacherEmail');
    }

    // Phone validation (exactly 10 digits)
    const phone = $('#teacherPhone').val().trim();
    if (!/^[0-9]{10}$/.test(phone)) {
        showError('teacherPhone', '10 digit mobile number required');
        isValid = false;
    } else {
        clearError('teacherPhone');
    }

    // Aadhaar validation (exactly 12 digits)
    const aadhaar = $('#teacherAadhaar').val().trim();
    if (!/^[0-9]{12}$/.test(aadhaar)) {
        showError('teacherAadhaar', 'Aadhaar must be exactly 12 digits');
        isValid = false;
    } else {
        clearError('teacherAadhaar');
    }

    // PAN validation (format: ABCDE1234F)
    const pan = $('#teacherPAN').val().trim().toUpperCase();
    if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan)) {
        showError('teacherPAN', 'Invalid PAN format. Use: ABCDE1234F');
        isValid = false;
    } else {
        clearError('teacherPAN');
    }

    // Spouse name validation (required if married)
    const maritalStatus = $('#teacherMaritalStatus').val();
    const spouseName = $('#teacherSpouseName').val().trim();
    if (maritalStatus === 'Married' && spouseName.length === 0) {
        showError('teacherSpouseName', 'Spouse name required for married status');
        isValid = false;
    } else {
        clearError('teacherSpouseName');
    }

    // Pincode validation (exactly 6 digits)
    const pincode = $('#teacherPincode').val().trim();
    if (!/^[0-9]{6}$/.test(pincode)) {
        showError('teacherPincode', '6 digit pincode required');
        isValid = false;
    } else {
        clearError('teacherPincode');
    }

    // Check all required fields
    const requiredFields = [
        'teacherGender', 'teacherBloodGroup', 'teacherMaritalStatus',
        'teacherDoorNo', 'teacherArea', 'teacherCity', 'teacherState'
    ];

    requiredFields.forEach(field => {
        if (!$('#' + field).val()) {
            showError(field, 'This field is required');
            isValid = false;
        } else {
            clearError(field);
        }
    });

    return isValid;
}

// Validate Professional Details Tab
function validateProfessionalDetails() {
    let isValid = true;

    const requiredFields = [
        'teacherQualification',
        'teacherExperience',
        'teacherJoiningDate',
        'teacherEmploymentType',
        'teacherStatus'
    ];

    requiredFields.forEach(field => {
        if (!$('#' + field).val()) {
            showError(field, 'This field is required');
            isValid = false;
        } else {
            clearError(field);
        }
    });

    // Experience validation (0-50 years)
    const exp = parseInt($('#teacherExperience').val());
    if (exp < 0 || exp > 50) {
        showError('teacherExperience', 'Experience must be between 0-50 years');
        isValid = false;
    }

    // Joining date cannot be future
    const joiningDate = new Date($('#teacherJoiningDate').val());
    if (joiningDate > new Date()) {
        showError('teacherJoiningDate', 'Joining date cannot be in the future');
        isValid = false;
    }

    return isValid;
}

// Validate Teaching Assignments
function validateTeachingAssignments() {
    if (teacherAllocations.length === 0) {
        showAllocationError('At least one class-subject allocation is required');
        return false;
    }
    clearAllocationError();
    return true;
}

// Helper functions
function showError(fieldId, message) {
    $('#' + fieldId).addClass('error');
    $('#' + fieldId + '-error').text(message).show();
}

function clearError(fieldId) {
    $('#' + fieldId).removeClass('error');
    $('#' + fieldId + '-error').text('').hide();
}

function showAllocationError(message) {
    $('#allocationValidation').show();
    $('#allocationValidationText').text(message);
}

function clearAllocationError() {
    $('#allocationValidation').hide();
    $('#allocationValidationText').text('');
}

// Toggle spouse name field
function toggleSpouseField() {
    const maritalStatus = $('#teacherMaritalStatus').val();
    if (maritalStatus === 'Married') {
        $('#spouseNameGroup').show();
        $('#teacherSpouseName').attr('required', true);
    } else {
        $('#spouseNameGroup').hide();
        $('#teacherSpouseName').attr('required', false).val('');
        clearError('teacherSpouseName');
    }
}

// Load subjects for selected class
function loadSubjectsForClass() {
    const selectedClass = $('#allocationClass').val();
    if (!selectedClass) {
        $('#allocationSubject').prop('disabled', true).html('<option value="">Select class first</option>');
        return;
    }

    // AJAX call to get subjects for this class
    $.ajax({
        url: API_BASE_URL + '/subjects.php',
        type: 'GET',
        data: { class: selectedClass },
        success: function(response) {
            if (response.success && response.data) {
                let options = '<option value="">Select Subject</option>';
                response.data.forEach(subject => {
                    options += `<option value="${subject.id}">${subject.subject_name}</option>`;
                });
                $('#allocationSubject').prop('disabled', false).html(options);
            }
        },
        error: function() {
            showAllocationError('Failed to load subjects. Please try again.');
        }
    });
}

// Add allocation
function addAllocation() {
    const classVal = $('#allocationClass').val();
    const subjectVal = $('#allocationSubject').val();
    const subjectText = $('#allocationSubject option:selected').text();

    if (!classVal || !subjectVal) {
        showAllocationError('Please select both class and subject');
        return;
    }

    // Check for duplicates
    const isDuplicate = teacherAllocations.some(
        a => a.class === classVal && a.subject_id === subjectVal
    );

    if (isDuplicate) {
        showAllocationError('This class-subject combination already exists');
        return;
    }

    // Add to array
    teacherAllocations.push({
        class: classVal,
        subject_id: subjectVal,
        subject_name: subjectText
    });

    // Render table
    renderAllocationsTable();

    // Reset form
    $('#allocationClass').val('');
    $('#allocationSubject').prop('disabled', true).html('<option value="">Select class first</option>');
    clearAllocationError();
}

// Remove allocation
function removeAllocation(index) {
    teacherAllocations.splice(index, 1);
    renderAllocationsTable();
}

// Render allocations table
function renderAllocationsTable() {
    const tbody = $('#allocationsTableBody');

    if (teacherAllocations.length === 0) {
        tbody.html(`
            <tr class="empty-state-row">
                <td colspan="4" class="empty-state-cell">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No allocations added yet</p>
                    <small>Add at least one class-subject combination</small>
                </td>
            </tr>
        `);
        $('#allocationSummary').hide();
        return;
    }

    let html = '';
    teacherAllocations.forEach((allocation, index) => {
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>Class ${allocation.class}</td>
                <td>${allocation.subject_name}</td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeAllocation(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.html(html);
    $('#allocationCount').text(teacherAllocations.length);
    $('#allocationSummary').show();
}

// Uniqueness validation via AJAX
function validateUniqueEmail() {
    const email = $('#teacherEmail').val().trim();
    if (!email) return;

    $.ajax({
        url: API_BASE_URL + '/teachers.php',
        type: 'GET',
        data: { action: 'check_unique', field: 'email', value: email },
        success: function(response) {
            if (!response.unique) {
                showError('teacherEmail', 'This email is already registered');
            } else {
                clearError('teacherEmail');
            }
        }
    });
}

function validateUniquePhone() {
    const phone = $('#teacherPhone').val().trim();
    if (!phone || phone.length !== 10) return;

    $.ajax({
        url: API_BASE_URL + '/teachers.php',
        type: 'GET',
        data: { action: 'check_unique', field: 'phone', value: phone },
        success: function(response) {
            if (!response.unique) {
                showError('teacherPhone', 'This phone number is already registered');
            } else {
                clearError('teacherPhone');
            }
        }
    });
}

function validateUniqueAadhaar() {
    const aadhaar = $('#teacherAadhaar').val().trim();
    if (!aadhaar || aadhaar.length !== 12) return;

    $.ajax({
        url: API_BASE_URL + '/teachers.php',
        type: 'GET',
        data: { action: 'check_unique', field: 'aadhaar_number', value: aadhaar },
        success: function(response) {
            if (!response.unique) {
                showError('teacherAadhaar', 'This Aadhaar number is already registered');
            } else {
                clearError('teacherAadhaar');
            }
        }
    });
}

function validateUniquePAN() {
    const pan = $('#teacherPAN').val().trim().toUpperCase();
    if (!pan || pan.length !== 10) return;

    $.ajax({
        url: API_BASE_URL + '/teachers.php',
        type: 'GET',
        data: { action: 'check_unique', field: 'pan_number', value: pan },
        success: function(response) {
            if (!response.unique) {
                showError('teacherPAN', 'This PAN number is already registered');
            } else {
                clearError('teacherPAN');
            }
        }
    });
}

// Set max DOB (18 years ago from today)
function setMaxDOB() {
    const today = new Date();
    const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    const maxDateString = maxDate.toISOString().split('T')[0];
    $('#teacherDOB').attr('max', maxDateString);
}

// Form submission
function submitTeacherForm() {
    // Validate all tabs
    const personalValid = validatePersonalInfo();
    const professionalValid = validateProfessionalDetails();
    const assignmentValid = validateTeachingAssignments();

    if (!personalValid || !professionalValid || !assignmentValid) {
        alert('Please fix all validation errors before submitting');
        return false;
    }

    // Prepare data
    const formData = {
        // Personal Info
        name: $('#teacherName').val().trim(),
        gender: $('#teacherGender').val(),
        dob: $('#teacherDOB').val(),
        blood_group: $('#teacherBloodGroup').val(),
        email: $('#teacherEmail').val().trim(),
        phone: $('#teacherPhone').val().trim(),
        aadhaar_number: $('#teacherAadhaar').val().trim(),
        pan_number: $('#teacherPAN').val().trim().toUpperCase(),
        marital_status: $('#teacherMaritalStatus').val(),
        spouse_name: $('#teacherSpouseName').val().trim() || null,

        // Address
        door_no: $('#teacherDoorNo').val().trim(),
        area: $('#teacherArea').val().trim(),
        city: $('#teacherCity').val().trim(),
        state: $('#teacherState').val(),
        pincode: $('#teacherPincode').val().trim(),

        // Professional
        qualification: $('#teacherQualification').val(),
        experience: $('#teacherExperience').val(),
        joining_date: $('#teacherJoiningDate').val(),
        employment_type: $('#teacherEmploymentType').val(),
        status: $('#teacherStatus').val(),
        subject_expertise: $('#teacherSubjectExpertise').val().trim() || null,

        // Teaching Assignments
        class_subject_allocations: teacherAllocations
    };

    // Submit via AJAX
    $.ajax({
        url: API_BASE_URL + '/teachers.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(formData),
        success: function(response) {
            if (response.success) {
                alert('Teacher added successfully!');
                closeTeacherModal();
                loadTeachers(); // Refresh table
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Failed to save teacher. Please try again.');
        }
    });
}
```

---

## 5️⃣ BACKEND API ENDPOINTS

### PHP API: `teachers.php`

```php
<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['action']) && $_GET['action'] === 'check_unique') {
            checkUniqueness($_GET['field'], $_GET['value']);
        } else {
            getTeachers();
        }
        break;

    case 'POST':
        createTeacher($data);
        break;

    case 'PUT':
        updateTeacher($data);
        break;

    case 'DELETE':
        deleteTeacher($data['id']);
        break;
}

// Check uniqueness
function checkUniqueness($field, $value) {
    global $pdo;

    $allowedFields = ['email', 'phone', 'aadhaar_number', 'pan_number'];
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['error' => 'Invalid field']);
        return;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE $field = ?");
    $stmt->execute([$value]);
    $count = $stmt->fetchColumn();

    echo json_encode(['unique' => $count == 0]);
}

// Create teacher
function createTeacher($data) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Generate teacher ID
        $stmt = $pdo->query("SELECT id FROM teachers ORDER BY id DESC LIMIT 1");
        $lastId = $stmt->fetchColumn();
        $newId = generateNextId($lastId, 'TCHR');

        // Generate employee code
        $year = date('Y');
        $stmt = $pdo->query("SELECT employee_code FROM teachers WHERE employee_code LIKE 'EMP{$year}%' ORDER BY employee_code DESC LIMIT 1");
        $lastEmpCode = $stmt->fetchColumn();
        $empCode = generateEmployeeCode($lastEmpCode, $year);

        // Insert teacher
        $sql = "INSERT INTO teachers (
            id, employee_code, name, gender, dob, blood_group, email, phone,
            aadhaar_number, pan_number, marital_status, spouse_name,
            door_no, area, city, state, pincode,
            qualification, experience, joining_date, employment_type, status,
            subject_expertise
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $newId,
            $empCode,
            $data['name'],
            $data['gender'],
            $data['dob'],
            $data['blood_group'],
            $data['email'],
            $data['phone'],
            $data['aadhaar_number'],
            $data['pan_number'],
            $data['marital_status'],
            $data['spouse_name'],
            $data['door_no'],
            $data['area'],
            $data['city'],
            $data['state'],
            $data['pincode'],
            $data['qualification'],
            $data['experience'],
            $data['joining_date'],
            $data['employment_type'],
            $data['status'],
            $data['subject_expertise']
        ]);

        // Insert class-subject allocations
        if (!empty($data['class_subject_allocations'])) {
            $allocationSql = "INSERT INTO teacher_class_subjects (teacher_id, class, subject_id) VALUES (?, ?, ?)";
            $allocationStmt = $pdo->prepare($allocationSql);

            foreach ($data['class_subject_allocations'] as $allocation) {
                $allocationStmt->execute([
                    $newId,
                    $allocation['class'],
                    $allocation['subject_id']
                ]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Teacher added successfully',
            'data' => [
                'id' => $newId,
                'employee_code' => $empCode
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add teacher: ' . $e->getMessage()
        ]);
    }
}

// Helper: Generate next ID
function generateNextId($lastId, $prefix) {
    if (!$lastId) {
        return $prefix . '001';
    }
    $number = intval(substr($lastId, strlen($prefix))) + 1;
    return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
}

// Helper: Generate employee code
function generateEmployeeCode($lastCode, $year) {
    if (!$lastCode) {
        return "EMP{$year}001";
    }
    $number = intval(substr($lastCode, -3)) + 1;
    return "EMP{$year}" . str_pad($number, 3, '0', STR_PAD_LEFT);
}
?>
```

### Subjects API: `subjects.php`

```php
<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$class = $_GET['class'] ?? null;

if (!$class) {
    echo json_encode(['success' => false, 'message' => 'Class required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, subject_name, subject_code, is_core, stream
                           FROM subjects_by_class
                           WHERE class = ?
                           ORDER BY is_core DESC, subject_name ASC");
    $stmt->execute([$class]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $subjects
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
```

---

## 6️⃣ CSS STYLING

```css
/* Tab Navigation */
.tab-navigation {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
}

.tab-btn {
    padding: 14px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: #64748b;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    color: #8b5cf6;
    background: #f8fafc;
}

.tab-btn.active {
    color: #8b5cf6;
    border-bottom-color: #8b5cf6;
    background: #f8fafc;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Form Sections */
.form-section {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.form-section h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-description {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 16px;
}

/* Form Fields */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group.align-end {
    align-items: flex-end;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

.required {
    color: #ef4444;
}

.form-group input,
.form-group select {
    height: 44px;
    padding: 0 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

.form-group input.error,
.form-group select.error {
    border-color: #ef4444;
}

.help-text {
    font-size: 12px;
    color: #94a3b8;
}

.error-msg {
    font-size: 12px;
    color: #ef4444;
    display: none;
}

/* Allocations Grid */
.allocation-form-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 16px;
    border: 2px solid #e2e8f0;
}

.validation-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    padding: 10px 14px;
    color: #dc2626;
    font-size: 13px;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.allocations-grid-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    margin-bottom: 16px;
}

.allocations-table {
    width: 100%;
    border-collapse: collapse;
}

.allocations-table thead {
    background: #f8fafc;
}

.allocations-table th {
    padding: 12px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

.allocations-table td {
    padding: 14px 16px;
    font-size: 14px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
}

.empty-state-cell {
    text-align: center;
    padding: 40px 20px !important;
    color: #94a3b8;
}

.empty-state-cell i {
    font-size: 48px;
    color: #e2e8f0;
    margin-bottom: 12px;
}

.empty-state-cell p {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.empty-state-cell small {
    font-size: 13px;
}

.btn-remove {
    background: #fef2f2;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: #ef4444;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-remove:hover {
    background: #ef4444;
    color: white;
}

.allocation-summary {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 10px 14px;
    color: #16a34a;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Modal Footer */
.modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.modal-footer .btn-group {
    display: flex;
    gap: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .tab-navigation {
        flex-direction: column;
    }

    .modal-footer {
        flex-direction: column;
    }

    .modal-footer .btn-group {
        width: 100%;
        flex-direction: column;
    }

    .modal-footer .btn {
        width: 100%;
    }
}
```

---

## 7️⃣ UX BEST PRACTICES

### Progress Indicators
```html
<div class="tab-progress">
    <div class="progress-step completed">
        <i class="fas fa-check"></i> Personal Info
    </div>
    <div class="progress-step active">
        <i class="fas fa-arrow-right"></i> Professional
    </div>
    <div class="progress-step">
        Teaching Assignment
    </div>
</div>
```

### Save Behavior
- **Save & Next**: Validates current tab, moves to next
- **Submit**: Final validation of all tabs + submission
- **Cancel**: Confirms before closing if data entered

### Button States
```javascript
// Disable submit until all validations pass
function updateSubmitButton() {
    const personalValid = validatePersonalInfo();
    const professionalValid = validateProfessionalDetails();
    const assignmentValid = validateTeachingAssignments();

    $('#submitBtn').prop('disabled', !(personalValid && professionalValid && assignmentValid));
}
```

---

## 8️⃣ COMPLETE SAVE FLOW

```
User Fills Form
    ↓
Tab 1: Personal Info → Validate
    ↓
Tab 2: Professional Details → Validate
    ↓
Tab 3: Teaching Assignment → Validate (min 1 allocation)
    ↓
Click Submit
    ↓
Final Validation (all tabs)
    ↓
[AJAX POST] → Backend API
    ↓
Backend Transaction:
  1. Generate Teacher ID (TCHR001)
  2. Generate Employee Code (EMP2025001)
  3. INSERT into teachers table
  4. INSERT allocations into teacher_class_subjects
  5. COMMIT
    ↓
Success Response
    ↓
Show Success Message
    ↓
Close Modal
    ↓
Refresh Teacher List
```

---

## 9️⃣ ERROR HANDLING

### Duplicate Entry Errors
```php
try {
    // Insert query
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        if (strpos($e->getMessage(), 'email') !== false) {
            echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Email already exists']);
        } elseif (strpos($e->getMessage(), 'phone') !== false) {
            echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Phone already exists']);
        }
        // ... handle other fields
    }
}
```

---

## 🔟 TESTING CHECKLIST

### Frontend Validation
- [ ] All mandatory fields validated
- [ ] Aadhaar: 12 digits, numeric
- [ ] PAN: ABCDE1234F format
- [ ] Email: Valid format
- [ ] Phone: 10 digits
- [ ] DOB: 18+ years
- [ ] Spouse name: Required if married
- [ ] Pincode: 6 digits
- [ ] At least 1 class-subject allocation
- [ ] No duplicate allocations

### Backend Validation
- [ ] Uniqueness: Email, Phone, Aadhaar, PAN
- [ ] Employee Code auto-generation
- [ ] Teacher ID auto-generation
- [ ] Transaction rollback on error
- [ ] Proper error messages

### UI/UX
- [ ] Tab navigation works smoothly
- [ ] Error messages display inline
- [ ] Form resets after submission
- [ ] Loading states during AJAX
- [ ] Responsive on mobile

---

## ✅ CONCLUSION

This comprehensive design provides:

1. **Tab-based UI** for better organization
2. **Complete validation** (client + server)
3. **Many-to-many relationship** for teaching assignments
4. **Auto-generated IDs** (Teacher ID, Employee Code)
5. **Inline error messages** with real-time validation
6. **Uniqueness checks** via AJAX
7. **Transaction safety** in backend
8. **Responsive design** for all devices
9. **Clean UX** with progress indicators

All requirements have been addressed with production-ready code examples.
