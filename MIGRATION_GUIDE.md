# Migration Guide: LocalStorage to Backend API

## 📘 Overview

This guide shows you how to migrate existing pages from localStorage to the backend API using jQuery.

---

## 🎯 Migration Steps

### Step 1: Include Required Scripts

Add to your HTML page (`<head>` section):

```html
<!-- jQuery (if not already included) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Main Application Script -->
<script src="../assets/js/app.js"></script>
```

### Step 2: Identify localStorage Usage

Look for patterns like:

```javascript
// Getting data
const students = JSON.parse(localStorage.getItem('edu_students') || '[]');

// Saving data
localStorage.setItem('edu_students', JSON.stringify(students));

// Removing data
localStorage.removeItem('edu_students');
```

### Step 3: Replace with API Calls

Use `EduManageApp` modules instead of localStorage.

---

## 📝 Example 1: Students Management

### Before (LocalStorage)

```javascript
// Load students
function loadStudents() {
    students = JSON.parse(localStorage.getItem('edu_students') || '[]');
    renderTable();
}

// Save student
function saveStudent(studentData) {
    const newId = 'STU' + Date.now();
    studentData.id = newId;
    students.push(studentData);
    localStorage.setItem('edu_students', JSON.stringify(students));
    renderTable();
}

// Update student
function updateStudent(id, studentData) {
    const index = students.findIndex(s => s.id === id);
    if (index !== -1) {
        students[index] = { ...students[index], ...studentData };
        localStorage.setItem('edu_students', JSON.stringify(students));
        renderTable();
    }
}

// Delete student
function deleteStudent(id) {
    students = students.filter(s => s.id !== id);
    localStorage.setItem('edu_students', JSON.stringify(students));
    renderTable();
}
```

### After (Backend API)

```javascript
// Load students
function loadStudents() {
    EduManageApp.Students.getList(
        {
            page: currentPage,
            perPage: pageSize,
            class: filterClass,
            section: filterSection,
            status: filterStatus
        },
        function(response) {
            students = response.data.students;
            totalPages = response.data.pagination.totalPages;
            renderTable();
        },
        function(error) {
            console.error('Error loading students:', error);
            EduManageApp.showNotification('Failed to load students', 'error');
        }
    );
}

// Save student
function saveStudent(studentData) {
    EduManageApp.Students.create(
        studentData,
        function(response) {
            EduManageApp.showNotification('Student saved successfully!', 'success');
            loadStudents(); // Reload from server
        },
        function(error) {
            EduManageApp.showNotification(error.message || 'Failed to save student', 'error');
        }
    );
}

// Update student
function updateStudent(id, studentData) {
    studentData.id = id;

    EduManageApp.Students.update(
        studentData,
        function(response) {
            EduManageApp.showNotification('Student updated successfully!', 'success');
            loadStudents(); // Reload from server
        },
        function(error) {
            EduManageApp.showNotification(error.message || 'Failed to update student', 'error');
        }
    );
}

// Delete student
function deleteStudent(id) {
    if (!confirm('Are you sure you want to delete this student?')) {
        return;
    }

    EduManageApp.Students.delete(
        id,
        function(response) {
            EduManageApp.showNotification('Student deleted successfully!', 'success');
            loadStudents(); // Reload from server
        },
        function(error) {
            EduManageApp.showNotification(error.message || 'Failed to delete student', 'error');
        }
    );
}
```

---

## 📝 Example 2: Attendance Management

### Before (LocalStorage)

```javascript
// Load attendance
function loadAttendance() {
    const attendance = JSON.parse(localStorage.getItem('edu_attendance') || '[]');
    renderAttendance(attendance);
}

// Mark attendance
function markAttendance(date, records) {
    const attendance = JSON.parse(localStorage.getItem('edu_attendance') || '[]');

    records.forEach(record => {
        const existingIndex = attendance.findIndex(a =>
            a.studentId === record.studentId && a.date === date
        );

        if (existingIndex !== -1) {
            attendance[existingIndex] = record;
        } else {
            attendance.push(record);
        }
    });

    localStorage.setItem('edu_attendance', JSON.stringify(attendance));
    renderAttendance(attendance);
}
```

### After (Backend API)

```javascript
// Load attendance
function loadAttendance(date, classVal, section) {
    $.ajax({
        url: '../backend/api/attendance.php',
        type: 'GET',
        data: {
            action: 'list',
            date: date,
            class: classVal,
            section: section
        },
        success: function(response) {
            if (response.success) {
                renderAttendance(response.data.records);
            } else {
                EduManageApp.showNotification(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading attendance:', error);
            EduManageApp.showNotification('Failed to load attendance', 'error');
        }
    });
}

// Mark attendance
function markAttendance(date, records) {
    $.ajax({
        url: '../backend/api/attendance.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            date: date,
            records: records
        }),
        success: function(response) {
            if (response.success) {
                EduManageApp.showNotification('Attendance marked successfully!', 'success');
                loadAttendance(date, currentClass, currentSection); // Reload
            } else {
                EduManageApp.showNotification(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error marking attendance:', error);
            EduManageApp.showNotification('Failed to mark attendance', 'error');
        }
    });
}
```

---

## 📝 Example 3: Statistics/Dashboard

### Before (LocalStorage)

```javascript
// Get statistics
function getStats() {
    const students = JSON.parse(localStorage.getItem('edu_students') || '[]');

    const stats = {
        total: students.length,
        active: students.filter(s => s.status === 'Active').length,
        male: students.filter(s => s.gender === 'Male').length,
        female: students.filter(s => s.gender === 'Female').length
    };

    renderStats(stats);
}
```

### After (Backend API)

```javascript
// Get statistics
function getStats(classVal, section) {
    EduManageApp.Students.getStats(
        {
            class: classVal,
            section: section
        },
        function(response) {
            renderStats(response.data);
        },
        function(error) {
            console.error('Error loading stats:', error);
        }
    );
}
```

---

## 🔄 Common Patterns

### Pattern 1: CRUD Operations

```javascript
// CREATE
EduManageApp.MODULE.create(data, successCallback, errorCallback);

// READ (List)
EduManageApp.MODULE.getList(params, successCallback, errorCallback);

// READ (Single)
EduManageApp.MODULE.getSingle(id, successCallback, errorCallback);

// UPDATE
EduManageApp.MODULE.update(data, successCallback, errorCallback);

// DELETE
EduManageApp.MODULE.delete(id, successCallback, errorCallback);
```

### Pattern 2: Search/Filter

```javascript
// Using GET parameters
EduManageApp.Students.getList(
    {
        page: 1,
        perPage: 10,
        search: searchQuery,
        class: classFilter,
        section: sectionFilter,
        status: statusFilter
    },
    function(response) {
        // Handle response
    }
);
```

### Pattern 3: Pagination

```javascript
let currentPage = 1;
let totalPages = 1;

function loadPage(page) {
    EduManageApp.Students.getList(
        {
            page: page,
            perPage: 10
        },
        function(response) {
            currentPage = response.data.pagination.page;
            totalPages = response.data.pagination.totalPages;

            // Render table
            renderTable(response.data.students);

            // Update pagination UI
            updatePaginationUI();
        }
    );
}

// Previous page
$('#prevBtn').click(function() {
    if (currentPage > 1) {
        loadPage(currentPage - 1);
    }
});

// Next page
$('#nextBtn').click(function() {
    if (currentPage < totalPages) {
        loadPage(currentPage + 1);
    }
});
```

---

## 🎨 Handling Async Operations

### Loading Indicators

```javascript
// Show loading
function showLoading() {
    $('#loadingSpinner').show();
    $('#dataTable').css('opacity', '0.5');
}

// Hide loading
function hideLoading() {
    $('#loadingSpinner').hide();
    $('#dataTable').css('opacity', '1');
}

// Use in API calls
function loadStudents() {
    showLoading();

    EduManageApp.Students.getList({page: 1},
        function(response) {
            hideLoading();
            renderTable(response.data.students);
        },
        function(error) {
            hideLoading();
            EduManageApp.showNotification('Error loading data', 'error');
        }
    );
}
```

### Global AJAX Handlers

Already included in `app.js`:

```javascript
$(document).ajaxStart(function() {
    $('body').addClass('loading');
});

$(document).ajaxStop(function() {
    $('body').removeClass('loading');
});
```

---

## ⚠️ Important Considerations

### 1. Error Handling

Always provide error callbacks:

```javascript
EduManageApp.Students.getList(params,
    function(response) {
        // Success
    },
    function(error) {
        // Handle error properly
        console.error('API Error:', error);
        EduManageApp.showNotification(error.message || 'An error occurred', 'error');
    }
);
```

### 2. Data Validation

Validate before sending to API:

```javascript
function validateStudentData(data) {
    if (!data.name || data.name.trim() === '') {
        EduManageApp.showNotification('Name is required', 'error');
        return false;
    }

    if (!data.class || data.class === '') {
        EduManageApp.showNotification('Class is required', 'error');
        return false;
    }

    // More validations...

    return true;
}

// Use before API call
if (validateStudentData(studentData)) {
    EduManageApp.Students.create(studentData, ...);
}
```

### 3. Optimistic Updates vs Server Refresh

**Option A: Optimistic Update** (faster UX)
```javascript
// Update UI immediately
students.push(newStudent);
renderTable();

// Then sync with server
EduManageApp.Students.create(newStudent, ...);
```

**Option B: Server Refresh** (more reliable)
```javascript
// Save to server first
EduManageApp.Students.create(newStudent,
    function(response) {
        // Then reload from server
        loadStudents();
    }
);
```

**Recommended**: Use Server Refresh for data integrity.

---

## 📋 Migration Checklist

For each page:

- [ ] Include `app.js` script
- [ ] Identify all `localStorage` calls
- [ ] Replace `getItem` with API `getList/getSingle`
- [ ] Replace `setItem` (create) with API `create`
- [ ] Replace `setItem` (update) with API `update`
- [ ] Replace `removeItem` with API `delete`
- [ ] Add proper error handling
- [ ] Add loading indicators
- [ ] Test all CRUD operations
- [ ] Test filters and pagination
- [ ] Test error scenarios
- [ ] Update UI to show API data
- [ ] Remove old localStorage code

---

## 🧪 Testing Your Migration

### Test Checklist

1. **Create Operation**
   - [ ] Can add new record
   - [ ] Validation works
   - [ ] Success message shows
   - [ ] Table refreshes
   - [ ] Data persists on page reload

2. **Read Operation**
   - [ ] Initial load works
   - [ ] Pagination works
   - [ ] Filters work
   - [ ] Search works
   - [ ] Data displays correctly

3. **Update Operation**
   - [ ] Can edit record
   - [ ] Changes save
   - [ ] Success message shows
   - [ ] Table updates

4. **Delete Operation**
   - [ ] Can delete record
   - [ ] Confirmation prompt shows
   - [ ] Success message shows
   - [ ] Table updates
   - [ ] Record gone on reload

5. **Error Handling**
   - [ ] Network errors show message
   - [ ] Validation errors show
   - [ ] Server errors handled
   - [ ] User not left in broken state

---

## 🎓 Example: Complete Page Migration

See `frontend/index.html` for a complete example of:
- Login with backend API
- Session management
- Error handling
- Loading states
- Navigation
- Logout

Use this as a reference when migrating other pages.

---

## 📞 Need Help?

- Check `README.md` for API documentation
- See `QUICKSTART.md` for quick examples
- Check browser console for errors
- Check PHP error log: `backend/config/error.log`

---

**Happy Migrating! 🚀**
