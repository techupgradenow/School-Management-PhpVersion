# 🔄 API Integration Pattern Guide

## 📝 Step-by-Step Integration Pattern

This document provides the exact pattern used to integrate the Students module with the backend API. Follow this same pattern for all remaining modules.

---

## ✅ INTEGRATION CHECKLIST (Per Module)

### Step 1: Include API Client
```html
<!-- Add this in the <head> section of your HTML file -->
<script src="../assets/js/app.js"></script>
```

### Step 2: Replace Load Function
**BEFORE (localStorage):**
```javascript
function loadStudents() {
    const data = localStorage.getItem('edu_students');
    students = JSON.parse(data || '[]');
    renderTable();
}
```

**AFTER (API):**
```javascript
function loadStudents() {
    // Use backend API
    if (typeof EduManageApp !== 'undefined' && EduManageApp.Students) {
        EduManageApp.Students.getList({ page: 1, perPage: 1000 },
            function(response) {
                // Success callback
                if (response.data && response.data.students) {
                    students = response.data.students;
                    renderTable();
                    renderStats();
                } else {
                    students = [];
                    renderTable();
                    renderStats();
                }
            },
            function(error) {
                // Error callback - fallback to localStorage for development
                console.warn('API not available:', error);
                const data = localStorage.getItem('edu_students');
                students = JSON.parse(data || '[]');
                renderTable();
                renderStats();
            }
        );
    } else {
        // Fallback to localStorage
        const data = localStorage.getItem('edu_students');
        students = JSON.parse(data || '[]');
        renderTable();
        renderStats();
    }
}
```

### Step 3: Replace Save/Create Function
**BEFORE (localStorage):**
```javascript
function saveStudent() {
    const data = {
        name: $('#studentName').val(),
        ...
    };

    if (editingIndex >= 0) {
        students[editingIndex] = data;
    } else {
        students.push(data);
    }

    localStorage.setItem('edu_students', JSON.stringify(students));
    renderTable();
    closeModal();
}
```

**AFTER (API):**
```javascript
function saveStudent() {
    const studentData = {
        name: $('#studentName').val().trim(),
        gender: $('#studentGender').val(),
        class: $('#studentClass').val(),
        section: $('#studentSection').val(),
        parent_name: $('#studentParent').val().trim(),
        contact: $('#studentContact').val().trim(),
        address: $('#studentAddress').val().trim(),
        dob: $('#studentDob').val(),
        joining_date: $('#studentJoiningDate').val(),
        blood_group: $('#studentBloodGroup').val(),
        status: $('#studentStatus').val(),
        photo: modalPhotoPreview || '',
        documents: collectDocumentsData()
    };

    // Client-side validation
    if (!studentData.name) {
        notify('Please enter student name', 'error');
        return;
    }

    // Use backend API
    if (typeof EduManageApp !== 'undefined' && EduManageApp.Students) {
        if (editingIndex >= 0) {
            // UPDATE
            studentData.id = students[editingIndex].id;

            EduManageApp.Students.update(studentData,
                function(response) {
                    notify('Student updated successfully!', 'success');
                    closeModal();
                    loadStudents(); // Reload from backend
                },
                function(error) {
                    notify(error.message || 'Failed to update student', 'error');
                }
            );
        } else {
            // CREATE
            EduManageApp.Students.create(studentData,
                function(response) {
                    notify('New student added successfully!', 'success');
                    closeModal();
                    loadStudents(); // Reload from backend
                },
                function(error) {
                    notify(error.message || 'Failed to create student', 'error');
                }
            );
        }
    } else {
        // Fallback to localStorage
        if (editingIndex >= 0) {
            students[editingIndex] = studentData;
        } else {
            studentData.id = 'STU-' + Date.now();
            students.push(studentData);
        }
        localStorage.setItem('edu_students', JSON.stringify(students));
        renderTable();
        renderStats();
        closeModal();
    }
}
```

### Step 4: Replace Delete Function
**BEFORE (localStorage):**
```javascript
function deleteStudent(idx) {
    if (!confirm('Are you sure?')) return;

    students.splice(idx, 1);
    localStorage.setItem('edu_students', JSON.stringify(students));
    renderTable();
}
```

**AFTER (API):**
```javascript
function deleteStudent(idx) {
    const student = students[idx];
    const studentName = student.name || 'this student';
    const studentId = student.id || '';

    if (!confirm(`Are you sure you want to delete "${studentName}"?`)) {
        notify('Deletion cancelled', 'info');
        return;
    }

    // Use backend API
    if (typeof EduManageApp !== 'undefined' && EduManageApp.Students) {
        EduManageApp.Students.delete(studentId,
            function(response) {
                notify(`Student "${studentName}" deleted successfully!`, 'success');
                loadStudents(); // Reload from backend
            },
            function(error) {
                notify(error.message || 'Failed to delete student', 'error');
            }
        );
    } else {
        // Fallback to localStorage
        students.splice(idx, 1);
        localStorage.setItem('edu_students', JSON.stringify(students));
        renderTable();
        renderStats();
        notify(`Student "${studentName}" deleted successfully!`, 'success');
    }
}
```

### Step 5: Remove Deprecated Save Function
```javascript
// OLD - Remove or comment out:
function saveToLocalStorage() {
    localStorage.setItem('edu_students', JSON.stringify(students));
}

// NEW - Keep for backward compatibility but mark as deprecated:
function saveToLocalStorage() {
    // Deprecated - API calls handle saving individual records
    // Keep for backward compatibility during migration
    localStorage.setItem('edu_students', JSON.stringify(students));
}
```

---

## 🔧 MODULE-SPECIFIC ADAPTATIONS

### Teachers Module
```javascript
// Replace 'Students' with 'Teachers'
EduManageApp.Teachers.getList(...)
EduManageApp.Teachers.create(...)
EduManageApp.Teachers.update(...)
EduManageApp.Teachers.delete(...)

// Storage key
const STORAGE_KEY = 'edu_teachers';
```

### Attendance Module
```javascript
EduManageApp.Attendance.getList(...)
EduManageApp.Attendance.mark(...) // Instead of create
EduManageApp.Attendance.update(...)
EduManageApp.Attendance.delete(...)

const STORAGE_KEY = 'edu_attendance';
```

### Exams Module
```javascript
EduManageApp.Exams.getList(...)
EduManageApp.Exams.create(...)
EduManageApp.Exams.enterMarks(...) // Additional function
EduManageApp.Exams.getResults(...)

const STORAGE_KEY = 'edu_exams';
```

### Fees Module
```javascript
EduManageApp.Fees.getPayments(...)
EduManageApp.Fees.recordPayment(...)
EduManageApp.Fees.getStructures(...)

const STORAGE_KEY = 'edu_fees';
```

### Library Module
```javascript
EduManageApp.Library.getBooks(...)
EduManageApp.Library.issueBook(...)
EduManageApp.Library.returnBook(...)

const STORAGE_KEY = 'edu_library';
```

### Transport Module
```javascript
EduManageApp.Transport.getRoutes(...)
EduManageApp.Transport.assignStudent(...)

const STORAGE_KEY = 'edu_transport';
```

### Hostel Module
```javascript
EduManageApp.Hostel.getRooms(...)
EduManageApp.Hostel.allocateRoom(...)
EduManageApp.Hostel.checkout(...)

const STORAGE_KEY = 'edu_hostel';
```

---

## 🎯 API CLIENT METHODS (app.js)

### Standard Methods Available:
```javascript
// GET list with pagination
Module.getList(params, successCallback, errorCallback)
// params: { page: 1, perPage: 10, class: '10', status: 'Active', ... }

// GET single record
Module.getSingle(id, successCallback, errorCallback)

// GET statistics
Module.getStats(successCallback, errorCallback)

// POST create new record
Module.create(data, successCallback, errorCallback)

// PUT update existing record
Module.update(data, successCallback, errorCallback)
// data must include 'id' field

// DELETE record
Module.delete(id, successCallback, errorCallback)

// Search
Module.search(query, successCallback, errorCallback)
```

### Callback Signature:
```javascript
// Success callback receives response object:
function successCallback(response) {
    // response.success === true
    // response.message = "Operation successful"
    // response.data = { ... }
}

// Error callback receives error object:
function errorCallback(error) {
    // error.success === false
    // error.message = "Error message"
    // error.errors = { field: "error" }
}
```

---

## 📐 DATA MAPPING (Frontend ↔ Backend)

### Students Example:
```javascript
// FRONTEND form fields → BACKEND API fields

Frontend Field          Backend Field
--------------          -------------
$('#studentName')    →  name
$('#studentGender')  →  gender
$('#studentClass')   →  class
$('#studentSection') →  section
$('#studentParent')  →  parent_name
$('#studentContact') →  contact
$('#studentAddress') →  address
$('#studentDob')     →  dob
$('#studentJoining') →  joining_date
$('#studentBlood')   →  blood_group
$('#studentStatus')  →  status
modalPhotoPreview    →  photo
documents array      →  documents
```

**Date Format Conversion:**
```javascript
// HTML date input: YYYY-MM-DD (e.g., "2024-01-15")
// Database: DATE type stores as YYYY-MM-DD
// Display: DD/MM/YYYY or "15 Jan 2024"

// No conversion needed for API submission (use HTML value as-is)
const dob = $('#studentDob').val(); // "2024-01-15" ✓

// Only convert for display
function formatDateForDisplay(dateString) {
    // "2024-01-15" → "15 Jan 2024"
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}
```

---

## ⚠️ COMMON PITFALLS & SOLUTIONS

### Pitfall 1: Not calling loadData() after save/delete
```javascript
// ❌ WRONG:
EduManageApp.Students.create(data, function() {
    closeModal();
    // Table not updated!
});

// ✅ CORRECT:
EduManageApp.Students.create(data, function() {
    closeModal();
    loadStudents(); // Reload from backend
});
```

### Pitfall 2: Forgetting to check if API is available
```javascript
// ❌ WRONG (will crash if app.js not loaded):
EduManageApp.Students.create(data, ...);

// ✅ CORRECT:
if (typeof EduManageApp !== 'undefined' && EduManageApp.Students) {
    EduManageApp.Students.create(data, ...);
} else {
    // Fallback to localStorage
}
```

### Pitfall 3: Not including record ID in update
```javascript
// ❌ WRONG:
const data = { name: 'Updated Name' };
EduManageApp.Students.update(data, ...);

// ✅ CORRECT:
const data = {
    id: 'STU-2025-001', // ← Must include ID!
    name: 'Updated Name'
};
EduManageApp.Students.update(data, ...);
```

### Pitfall 4: Incorrect field names
```javascript
// ❌ WRONG (frontend field names):
const data = {
    studentName: 'John',    // ❌
    parentName: 'Mr. John'  // ❌
};

// ✅ CORRECT (backend API field names):
const data = {
    name: 'John',          // ✅
    parent_name: 'Mr. John' // ✅
};
```

### Pitfall 5: Not handling errors
```javascript
// ❌ WRONG:
EduManageApp.Students.create(data, function(response) {
    notify('Success!', 'success');
});
// If API fails, user sees nothing!

// ✅ CORRECT:
EduManageApp.Students.create(data,
    function(response) {
        notify('Success!', 'success');
    },
    function(error) {
        notify(error.message || 'Failed to create student', 'error');
    }
);
```

---

## 🧪 TESTING EACH INTEGRATION

### Checklist Per Module:
1. ✅ Include app.js
2. ✅ Replace loadData() function
3. ✅ Replace save/create function
4. ✅ Replace update function
5. ✅ Replace delete function
6. ✅ Test create with valid data
7. ✅ Test create with missing fields
8. ✅ Test update operation
9. ✅ Test delete operation
10. ✅ Test list/load operation
11. ✅ Verify database records
12. ✅ Test error handling
13. ✅ Test fallback to localStorage

---

## 📊 INTEGRATION PRIORITY

**Recommended Order:**
1. ✅ Students (COMPLETED)
2. ⏳ Teachers (similar structure)
3. ⏳ Attendance (has special "mark" function)
4. ⏳ Exams (has "enter marks" function)
5. ⏳ Fees (has "record payment" function)
6. ⏳ Library (has "issue/return" functions)
7. ⏳ Transport (has "assign student" function)
8. ⏳ Hostel (has "allocate room" function)

---

## 🚀 QUICK START FOR NEW MODULE

1. Copy this template:
```javascript
// Include app.js in HTML
<script src="../assets/js/app.js"></script>

// Load function
function loadRecords() {
    if (typeof EduManageApp !== 'undefined' && EduManageApp.MODULE) {
        EduManageApp.MODULE.getList({ page: 1, perPage: 1000 },
            function(response) {
                records = response.data.MODULE || [];
                renderTable();
            },
            function(error) {
                console.warn('API not available:', error);
                records = JSON.parse(localStorage.getItem('STORAGE_KEY') || '[]');
                renderTable();
            }
        );
    } else {
        records = JSON.parse(localStorage.getItem('STORAGE_KEY') || '[]');
        renderTable();
    }
}

// Save function
function saveRecord() {
    const data = { /* collect form data */ };

    if (typeof EduManageApp !== 'undefined' && EduManageApp.MODULE) {
        if (editingIndex >= 0) {
            data.id = records[editingIndex].id;
            EduManageApp.MODULE.update(data,
                function() { notify('Updated!', 'success'); closeModal(); loadRecords(); },
                function(error) { notify(error.message, 'error'); }
            );
        } else {
            EduManageApp.MODULE.create(data,
                function() { notify('Created!', 'success'); closeModal(); loadRecords(); },
                function(error) { notify(error.message, 'error'); }
            );
        }
    } else {
        // localStorage fallback
    }
}

// Delete function
function deleteRecord(idx) {
    const record = records[idx];
    if (!confirm('Delete?')) return;

    if (typeof EduManageApp !== 'undefined' && EduManageApp.MODULE) {
        EduManageApp.MODULE.delete(record.id,
            function() { notify('Deleted!', 'success'); loadRecords(); },
            function(error) { notify(error.message, 'error'); }
        );
    } else {
        // localStorage fallback
    }
}
```

2. Replace placeholders:
   - `MODULE` → `Students`, `Teachers`, `Attendance`, etc.
   - `records` → `students`, `teachers`, `attendance`, etc.
   - `STORAGE_KEY` → `'edu_students'`, `'edu_teachers'`, etc.

3. Add module-specific field mappings

4. Test all operations

---

**Powered by UpgradeNow Technologies**
