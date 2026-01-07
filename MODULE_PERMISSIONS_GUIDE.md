# Module Access & Permissions System Guide
## EduManage Pro - School Management System

This guide explains how the Module Access and Permissions system works in User Management.

---

## Overview

The User Management system now includes:
1. **Module Access Tab** - Select which modules a user can access
2. **Permissions Tab** - Configure detailed permissions (View, Create, Edit, Delete, Export) for each module

---

## How It Works

### 1. Available Modules

The system has 12 modules:

| Module | Icon | Description |
|--------|------|-------------|
| Dashboard | 📊 | Overview & analytics |
| Students | 🎓 | Student management |
| Teachers | 👨‍🏫 | Teacher management |
| Classes | 🚪 | Class setup |
| Subjects | 📖 | Subject curriculum |
| Exams | 📋 | Exam management |
| Attendance | 📅 | Attendance tracking |
| Fees | 💵 | Fee collection |
| Library | 📚 | Library resources |
| Transport | 🚌 | Transport routes |
| Reports | 📈 | Analytics & reports |
| Settings | ⚙️ | System config |

### 2. Creating/Editing Users

When you create or edit a user:

**Step 1: User Details Tab**
- Fill in Name, Username, Email, Role, Status, Password

**Step 2: Module Access Tab**
- Click on module cards to select/deselect which modules the user can access
- Selected modules appear highlighted with a checkmark
- When you select a module, it automatically gets "View" permission

**Step 3: Permissions Tab**
- Fine-tune permissions for each module with 5 permission types:
  - **View**: Can see the module and its data
  - **Create**: Can add new records
  - **Edit**: Can modify existing records
  - **Delete**: Can remove records
  - **Export**: Can export data to files

**Quick Actions Available:**
- **Apply Role Defaults**: Auto-assigns permissions based on the selected role
- **Select All**: Grants all permissions to all modules
- **Clear All**: Removes all permissions

---

## Database Structure

### Users Table
```sql
users (
  id VARCHAR(50),           -- Display ID (USR001, USR002, etc.)
  uuid CHAR(36),            -- Internal unique ID (never exposed)
  name VARCHAR(255),
  username VARCHAR(100),
  email VARCHAR(255),
  password VARCHAR(255),    -- Bcrypt hashed
  role ENUM(...),
  status ENUM('Active','Inactive'),
  ...
)
```

### User Permissions Table
```sql
user_permissions (
  id INT AUTO_INCREMENT,
  user_uuid CHAR(36),       -- FK to users.uuid (CASCADE DELETE)
  module VARCHAR(100),      -- Module name (dashboard, students, etc.)
  can_view TINYINT(1),
  can_create TINYINT(1),
  can_edit TINYINT(1),
  can_delete TINYINT(1),
  can_export TINYINT(1),
  UNIQUE KEY (user_uuid, module)
)
```

### Key Points:
- ✅ Permissions stored in separate table (normalized)
- ✅ Foreign key with CASCADE DELETE (automatic cleanup)
- ✅ UUID never exposed in API responses
- ✅ One row per user-module combination

---

## How Permissions Are Stored

### Example: Teacher with Limited Permissions

**Frontend sends:**
```json
{
  "action": "create",
  "name": "John Teacher",
  "username": "jteacher",
  "email": "john@school.edu",
  "password": "Teacher@123",
  "role": "Teacher",
  "permissions": {
    "dashboard": {
      "view": true,
      "create": false,
      "edit": false,
      "delete": false,
      "export": true
    },
    "students": {
      "view": true,
      "create": true,
      "edit": true,
      "delete": false,
      "export": true
    },
    "attendance": {
      "view": true,
      "create": true,
      "edit": true,
      "delete": false,
      "export": false
    }
  }
}
```

**Database stores (user_permissions table):**
```
+----------+---------+----------+-----------+----------+-----------+
| module   | can_view| can_create| can_edit | can_delete| can_export|
+----------+---------+-----------+----------+-----------+-----------+
| dashboard|    1    |     0     |    0     |     0     |     1     |
| students |    1    |     1     |    1     |     0     |     1     |
| attendance|   1    |     1     |    1     |     0     |     0     |
+----------+---------+-----------+----------+-----------+-----------+
```

**API returns (login/get user):**
```json
{
  "id": "USR004",
  "name": "John Teacher",
  "username": "jteacher",
  "role": "Teacher",
  "permissions": {
    "dashboard": {"view": true, "create": false, "edit": false, "delete": false, "export": true},
    "students": {"view": true, "create": true, "edit": true, "delete": false, "export": true},
    "attendance": {"view": true, "create": true, "edit": true, "delete": false, "export": false}
  }
}
```

---

## Role-Based Default Permissions

### SuperAdmin
- ✅ All permissions on all modules
- Full system control

### Admin
- ✅ All permissions except Delete on User Management
- Can manage most system operations

### Teacher
**Default access:**
- Dashboard (view, export)
- Students (view, create, edit, export)
- Attendance (view, create, edit)
- Exams (view, create, edit)
- Subjects (view, create, edit)
- Classes (view)

### Student
**Default access:**
- Dashboard (view)
- Attendance (view)
- Exams (view)
- Fees (view)
- Library (view)

---

## Testing Module Access & Permissions

### Test 1: Create User with Permissions

1. Navigate to User Management
2. Click "Add New User"
3. Fill in details:
   - Name: Test User
   - Username: testuser
   - Email: test@test.com
   - Password: Test@12345
   - Role: Teacher
4. Go to "Module Access" tab
5. Select modules: Dashboard, Students, Attendance
6. Go to "Permissions" tab
7. Check permissions:
   - Dashboard: View ✅, Export ✅
   - Students: View ✅, Create ✅, Edit ✅, Export ✅
   - Attendance: View ✅, Create ✅, Edit ✅
8. Click "Save User"
9. Verify in database:
```bash
SELECT * FROM user_permissions WHERE user_uuid = (SELECT uuid FROM users WHERE username = 'testuser');
```

### Test 2: Edit User Permissions

1. Click "Edit" on existing user
2. Go to "Permissions" tab
3. Modify checkboxes
4. Click "Update User"
5. Verify changes reflected in database

### Test 3: Login with Limited Permissions

1. Logout
2. Login as user with limited permissions
3. Verify only permitted modules are accessible

---

## API Endpoints

### List Users (with permissions)
```http
GET /backend/api/users.php?action=list
```
Response includes permissions for each user.

### Get User
```http
GET /backend/api/users.php?action=get&id=USR001
```
Response includes full permissions object.

### Create User
```http
POST /backend/api/users.php
{
  "action": "create",
  "name": "...",
  "permissions": {...}
}
```

### Update User
```http
POST /backend/api/users.php
{
  "action": "update",
  "id": "USR001",
  "permissions": {...}
}
```

### Login
```http
POST /backend/api/auth.php
{
  "action": "login",
  "username": "...",
  "password": "..."
}
```
Response includes user's permissions.

---

## Troubleshooting

### Issue: Permissions not showing when editing user
**Solution**: Check that user has permissions in database:
```sql
SELECT * FROM user_permissions WHERE user_uuid = (SELECT uuid FROM users WHERE id = 'USR003');
```

### Issue: Module cards not highlighting
**Solution**:
- Permissions exist but selectedModules array not populated
- Fixed in openEditModal() function - auto-populates based on permissions

### Issue: Changes not saving
**Solution**: Check browser console for API errors:
- Open DevTools (F12)
- Go to Network tab
- Look for failed requests to users.php

### Issue: User can't login after creation
**Solution**: Check password is properly hashed:
```sql
SELECT LEFT(password, 10) FROM users WHERE id = 'USR004';
-- Should show: $2y$10$... (bcrypt hash)
```

---

## Code Flow

### 1. Loading User for Edit
```javascript
openEditModal(userId)
  ↓
Load user from users array
  ↓
permissions = user.permissions
  ↓
selectedModules = Object.keys(permissions).filter(has any permission)
  ↓
renderModules() + renderPermissions()
```

### 2. Saving User
```javascript
saveUser()
  ↓
Collect form data + permissions + selectedModules
  ↓
POST to /backend/api/users.php
  ↓
Backend: savePermissionsToTable(uuid, permissions)
  ↓
Inserts into user_permissions table
  ↓
Success response
```

### 3. Login
```javascript
Login API call
  ↓
Backend: getPermissionsFromTable(uuid)
  ↓
SELECT from user_permissions WHERE user_uuid = ?
  ↓
Format as JSON object
  ↓
Return in login response
```

---

## Files Involved

| File | Purpose |
|------|---------|
| `frontend/pages/users.html` | User Management UI |
| `backend/api/users.php` | User CRUD + Permissions API |
| `backend/api/auth.php` | Login + Permissions loading |
| `backend/database/migration_uuid_permissions.sql` | Database schema |

---

## Security Features

- ✅ UUID never exposed in API (internal key only)
- ✅ Passwords bcrypt hashed
- ✅ SQL injection protection (prepared statements)
- ✅ Foreign key CASCADE (data integrity)
- ✅ Role-based access control
- ✅ Session management

---

**Last Updated**: December 2025
**Version**: 2.0 - UUID + Separate Permissions Table
