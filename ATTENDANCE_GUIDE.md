# Attendance Management System Guide
## EduManage Pro - School Management System

This guide explains the complete Attendance Management functionality with backend API integration.

---

## Overview

The Attendance Management system allows teachers and administrators to:
1. **Mark Daily Attendance** - Record student attendance as Present, Absent, or Late
2. **View Statistics** - See real-time attendance stats (Present, Absent, Late, Attendance %)
3. **Filter by Class/Section** - Select specific class and section
4. **Save to Database** - Persist attendance records to MySQL database
5. **Load Saved Attendance** - Retrieve previously saved attendance
6. **Export to CSV** - Download attendance reports as Excel files

---

## Features Implemented

### ✅ Backend API Integration
- **Students API**: Loads actual students from database (`backend/api/students.php`)
- **Attendance API**: Full CRUD operations for attendance records (`backend/api/attendance.php`)
- **Real-time Data**: No more localStorage - all data saved to MySQL database
- **Permission-based Access**: Respects user permissions from User Management module

### ✅ Frontend Features
- **Auto-load Students**: Fetches students from database on page load
- **Dynamic Filters**: Populates Class and Section dropdowns based on actual student data
- **Real-time Stats**: Updates Present/Absent/Late counts as you mark attendance
- **Auto-save Detection**: Automatically loads saved attendance when changing date/class/section
- **Batch Operations**: "All Present", "All Absent", "Clear All" buttons
- **Export to CSV**: Download attendance with BOM for proper Excel compatibility

### ✅ Database Structure
```sql
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Half Day', 'Leave') NOT NULL,
    remarks TEXT NULL,
    marked_by VARCHAR(50) NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, date),
    INDEX idx_date (date),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;
```

---

## How to Use

### Step 1: Access Attendance Page
Navigate to: `http://localhost/School-Management-PhpVersion/frontend/pages/attendance.html`

### Step 2: Select Date, Class, and Section
1. **Date**: Select the date for attendance (defaults to today)
2. **Class**: Choose class (e.g., Class 10)
3. **Section**: Choose section (e.g., Section A)

The page will automatically load students for that class and section.

### Step 3: Mark Attendance
Click the buttons for each student:
- **✓ (Green)**: Mark as Present
- **✗ (Red)**: Mark as Absent
- **🕐 (Orange)**: Mark as Late

**Quick Actions:**
- **All Present**: Mark all visible students as present
- **All Absent**: Mark all visible students as absent
- **Clear**: Reset all attendance marks

### Step 4: Save Attendance
Click the **"Save Attendance"** button to save to database.

### Step 5: Load Saved Attendance
Click the **"Load Saved"** button to retrieve previously saved attendance for the selected date/class/section.

### Step 6: Export to CSV
Click the **"Export"** button to download attendance as an Excel-compatible CSV file.

---

## API Endpoints

### 1. Load Students
```http
GET /backend/api/students.php?action=list
```

**Response:**
```json
{
  "success": true,
  "data": {
    "students": [
      {
        "id": "STU001",
        "name": "Aarav Sharma",
        "class": "Class 10",
        "section": "A",
        "gender": "Male",
        "roll_no": "1"
      }
    ]
  }
}
```

### 2. Save Attendance
```http
POST /backend/api/attendance.php
Content-Type: application/json

{
  "date": "2025-12-20",
  "records": [
    {
      "student_id": "STU001",
      "status": "Present"
    },
    {
      "student_id": "STU002",
      "status": "Absent"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Attendance marked successfully for 2 student(s)",
  "data": {
    "success_count": 2,
    "errors": []
  }
}
```

### 3. Load Saved Attendance
```http
GET /backend/api/attendance.php?action=list&date=2025-12-20&class=Class%2010&section=A
```

**Response:**
```json
{
  "success": true,
  "message": "Attendance records fetched successfully",
  "data": {
    "records": [
      {
        "id": 1,
        "student_id": "STU001",
        "date": "2025-12-20",
        "status": "Present",
        "student_name": "Aarav Sharma",
        "class": "Class 10",
        "section": "A",
        "roll_no": "1"
      }
    ],
    "date": "2025-12-20"
  }
}
```

### 4. Get Attendance Statistics
```http
GET /backend/api/attendance.php?action=stats&date=2025-12-20&class=Class%2010&section=A
```

**Response:**
```json
{
  "success": true,
  "message": "Statistics fetched successfully",
  "data": {
    "date": "2025-12-20",
    "total": 10,
    "present": 8,
    "absent": 1,
    "late": 1,
    "percentage": 80.00
  }
}
```

---

## Permission-Based Access

The system respects permissions from the User Management module:

### View Permission
- Can view attendance records
- Can load saved attendance
- **Cannot** mark or save attendance

### Create/Edit Permission
- Can mark attendance (Present/Absent/Late)
- Can use batch actions (All Present, All Absent)
- Can save attendance to database

### Export Permission
- Can export attendance to CSV

### Example Permission Check
```javascript
let ATT_PERMS = { view: true, create: true, edit: true, delete: false, export: true };
const rawPerms = localStorage.getItem('edu_permissions');
if (rawPerms) {
    const perms = JSON.parse(rawPerms);
    if (perms && perms.attendance) {
        ATT_PERMS = perms.attendance;
    }
}
```

---

## Testing Guide

### Test 1: Load Students
1. Open attendance page
2. Verify students are loaded from database
3. Check that Class and Section dropdowns are populated

**Expected:**
- Students from database appear in table
- Dropdowns show actual classes/sections

### Test 2: Mark Attendance
1. Select Class 10, Section A
2. Mark some students as Present, some as Absent, one as Late
3. Verify stats update in real-time

**Expected:**
- Stats show: Present count, Absent count, Late count
- Attendance rate percentage calculates correctly

### Test 3: Save Attendance
1. Mark attendance for all students
2. Click "Save Attendance"
3. Check database for records

**SQL Check:**
```sql
SELECT * FROM attendance WHERE date = CURDATE() AND student_id IN (
  SELECT id FROM students WHERE class = 'Class 10' AND section = 'A'
);
```

**Expected:**
- Success notification appears
- Records inserted/updated in database

### Test 4: Load Saved Attendance
1. Refresh the page
2. Select same date, class, section
3. Page should auto-load saved attendance

**Expected:**
- Previously marked attendance appears automatically
- Stats match saved data

### Test 5: Export to CSV
1. Mark attendance for a class
2. Click "Export" button
3. Open downloaded CSV in Excel

**Expected:**
- CSV downloads with filename: `Attendance_Class10_A_2025-12-20.csv`
- Excel opens file correctly with UTF-8 encoding
- All student data and attendance status included

### Test 6: Batch Operations
1. Select a class with multiple students
2. Click "All Present"
3. Verify all students marked present
4. Click "Clear All"
5. Verify all attendance cleared

**Expected:**
- All Present: All students show green checkmark
- Clear All: All attendance marks removed

### Test 7: Permission Testing
1. Login as user with view-only attendance permission
2. Try to mark attendance

**Expected:**
- Save button is disabled
- Marking buttons don't work
- Error message: "You do not have permission to save attendance"

---

## Database Queries

### Check Today's Attendance
```sql
SELECT
    a.*,
    s.name as student_name,
    s.class,
    s.section
FROM attendance a
JOIN students s ON a.student_id = s.id
WHERE DATE(a.date) = CURDATE()
ORDER BY s.class, s.section, s.roll_no;
```

### Get Attendance Summary for a Date
```sql
SELECT
    status,
    COUNT(*) as count
FROM attendance
WHERE DATE(date) = '2025-12-20'
GROUP BY status;
```

### Get Student Attendance History
```sql
SELECT
    date,
    status,
    remarks
FROM attendance
WHERE student_id = 'STU001'
AND date BETWEEN '2025-12-01' AND '2025-12-31'
ORDER BY date DESC;
```

### Get Class Attendance Rate
```sql
SELECT
    s.class,
    s.section,
    COUNT(*) as total_records,
    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
    ROUND(
        (SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
        2
    ) as attendance_rate
FROM attendance a
JOIN students s ON a.student_id = s.id
WHERE DATE(a.date) BETWEEN '2025-12-01' AND '2025-12-31'
GROUP BY s.class, s.section
ORDER BY s.class, s.section;
```

---

## Troubleshooting

### Issue: Students not loading
**Solution:**
1. Check that students exist in database:
   ```sql
   SELECT COUNT(*) FROM students;
   ```
2. Verify students API is working:
   ```
   http://localhost/School-Management-PhpVersion/backend/api/students.php?action=list
   ```
3. Check browser console for errors (F12 → Console tab)

### Issue: Attendance not saving
**Solution:**
1. Check that date, class, and section are selected
2. Verify at least one student has attendance marked
3. Check browser console for API errors
4. Verify attendance table exists:
   ```sql
   SHOW TABLES LIKE 'attendance';
   ```

### Issue: Permission errors
**Solution:**
1. Login with proper user account
2. Check user permissions in User Management
3. Verify attendance permissions are granted:
   ```sql
   SELECT * FROM user_permissions
   WHERE user_uuid = (SELECT uuid FROM users WHERE id = 'USR001')
   AND module = 'attendance';
   ```

### Issue: Export not working
**Solution:**
1. Check that export permission is granted
2. Verify class and section are selected
3. Check browser console for errors

---

## File Structure

```
frontend/pages/
  ├── attendance.html          ← Main attendance page (NEW - Full API integration)
  └── attendance_old_backup.html  ← Backup of old localStorage version

backend/api/
  ├── attendance.php           ← Attendance CRUD API
  └── students.php             ← Students API

backend/database/
  ├── schema.sql               ← Database schema (includes attendance table)
  └── check_attendance_data.php ← Utility to check database data

database/
  └── schema.sql               ← Main database schema
```

---

## Key Improvements Over Previous Version

| Feature | Old Version (localStorage) | New Version (API) |
|---------|---------------------------|-------------------|
| **Data Storage** | Browser localStorage | MySQL Database |
| **Students Source** | Hardcoded/localStorage | Live API (`students.php`) |
| **Attendance Persistence** | Per-browser only | Centralized database |
| **Multi-user Support** | No | Yes (tracked by `marked_by`) |
| **Permission System** | Basic | Full integration with User Management |
| **Class/Section Filters** | Hardcoded options | Dynamic from actual data |
| **Data Export** | Local data only | Database records |
| **Concurrent Access** | Not supported | Fully supported |
| **Audit Trail** | None | Tracked (`marked_by`, `marked_at`) |

---

## Security Features

- ✅ **SQL Injection Protection**: Prepared statements in all queries
- ✅ **Permission Validation**: Backend checks user permissions before save
- ✅ **Foreign Key Constraints**: Ensures data integrity (CASCADE DELETE)
- ✅ **Audit Logging**: Tracks who marked attendance and when
- ✅ **Session Validation**: Requires active user session
- ✅ **CORS Configured**: Allows frontend-backend communication

---

## Performance Optimizations

- ✅ **Indexed Columns**: `date`, `student_id`, `status` for fast queries
- ✅ **Unique Constraint**: `(student_id, date)` prevents duplicates
- ✅ **Batch Insert/Update**: Single API call for all attendance records
- ✅ **Optimized Queries**: JOINs with proper indexes
- ✅ **Auto-load**: Fetches attendance automatically on filter change

---

**Last Updated**: December 2025
**Version**: 3.0 - Full Backend Integration

---

## Quick Start

1. Ensure XAMPP is running (Apache + MySQL)
2. Navigate to: `http://localhost/School-Management-PhpVersion/frontend/pages/attendance.html`
3. Select Date, Class, Section
4. Mark attendance
5. Click "Save Attendance"
6. Done! ✅
