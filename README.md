# EduManage Pro - School Management System

**Enterprise-Quality Architecture with Clean Frontend/Backend Separation**

---

## 📋 Project Overview

EduManage Pro is a comprehensive school management system refactored with strict separation of concerns:

- **Frontend**: Pure HTML, CSS, and jQuery for UI/UX
- **Backend**: PHP with MySQL for business logic and data management
- **Communication**: RESTful APIs with JSON responses
- **Architecture**: MVC-friendly, scalable, and maintainable

### ✅ Key Features

- Student Management (CRUD operations)
- Teacher Management
- Attendance Tracking
- Exam & Grade Management
- Admit Card Generation
- Timetable Management
- Fee Collection & Management
- Library Management
- Transport Management
- Hostel Management
- User Authentication & Role-Based Access Control (RBAC)
- Activity Logging
- Notifications System

---

## 📁 Project Structure

```
/project-root
│
├── frontend/                    # Frontend Application
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css       # All styles (unchanged)
│   │   ├── js/
│   │   │   └── app.js          # jQuery-based API communication
│   │   └── images/
│   │
│   ├── pages/                   # All HTML pages
│   │   ├── dashboard.html
│   │   ├── students.html
│   │   ├── teachers.html
│   │   ├── attendance.html
│   │   ├── exams.html
│   │   ├── admitcard.html
│   │   ├── timetable.html
│   │   ├── fees.html
│   │   ├── library.html
│   │   ├── transport.html
│   │   ├── hostel.html
│   │   ├── reports.html
│   │   ├── settings.html
│   │   └── users.html
│   │
│   └── index.html               # Main entry point with jQuery login
│
├── backend/                     # Backend Application
│   ├── config/
│   │   └── db.php              # Database configuration & connection
│   │
│   ├── api/                    # RESTful API Endpoints
│   │   ├── auth.php            # Authentication (login/logout/password change)
│   │   ├── students.php        # Students CRUD operations
│   │   ├── teachers.php        # Teachers CRUD operations
│   │   ├── attendance.php      # Attendance management
│   │   ├── exams.php           # Exams & grades
│   │   ├── fees.php            # Fee management
│   │   ├── library.php         # Library operations
│   │   ├── transport.php       # Transport routes & assignments
│   │   ├── hostel.php          # Hostel management
│   │   └── notifications.php   # Notifications
│   │
│   ├── controllers/            # Business logic controllers (optional)
│   ├── models/                 # Data models (optional)
│   └── helpers/
│       └── functions.php       # Helper functions (sanitization, validation, etc.)
│
└── database/
    └── schema.sql              # Complete database schema with all tables
```

---

## 🚀 Installation & Setup

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- jQuery 3.7.1+ (included via CDN)

### Step 1: Clone/Extract Project

```bash
# Extract the project to your web server directory
# Example: C:/xampp/htdocs/edumanage-pro (Windows)
# Example: /var/www/html/edumanage-pro (Linux)
```

### Step 2: Database Setup

1. Open phpMyAdmin or MySQL command line
2. Import the database schema:

```sql
SOURCE /path/to/database/schema.sql;
```

Or manually:
- Open `database/schema.sql`
- Copy and run all SQL commands in phpMyAdmin

3. The database will be created with:
   - Database name: `edumanage_pro`
   - Default users:
     - **SuperAdmin**: Username: `superadmin`, Password: `super@123`
     - **Admin**: Username: `admin`, Password: `admin123`

### Step 3: Configure Database Connection

Edit `backend/config/db.php`:

```php
define('DB_HOST', 'localhost');        // Your MySQL host
define('DB_USER', 'root');             // Your MySQL username
define('DB_PASS', '');                 // Your MySQL password
define('DB_NAME', 'edumanage_pro');    // Database name
```

### Step 4: Start the Application

1. **Start your web server** (Apache/Nginx)
2. **Navigate to the application**:
   ```
   http://localhost/edumanage-pro/frontend/
   ```

3. **Login** with default credentials:
   - Username: `admin`
   - Password: `admin123`

---

## 🔧 API Documentation

### Base URL

```
http://localhost/edumanage-pro/backend/api/
```

### Authentication API (`auth.php`)

#### Login
```
POST /backend/api/auth.php
Content-Type: application/json

{
    "action": "login",
    "username": "admin",
    "password": "admin123",
    "remember_me": false
}

Response:
{
    "success": true,
    "message": "Login successful! Welcome to EduManage Pro.",
    "data": {
        "user": {
            "id": "USR001",
            "name": "School Admin",
            "username": "admin",
            "email": "admin@edumanage.edu",
            "role": "Admin",
            "permissions": {}
        }
    }
}
```

#### Logout
```
POST /backend/api/auth.php
Content-Type: application/json

{
    "action": "logout"
}
```

#### Change Password
```
POST /backend/api/auth.php
Content-Type: application/json

{
    "action": "change_password",
    "current_password": "admin123",
    "new_password": "newpassword123",
    "confirm_password": "newpassword123"
}
```

### Students API (`students.php`)

#### Get Students List
```
GET /backend/api/students.php?action=list&page=1&perPage=10&class=10&section=A&status=Active

Response:
{
    "success": true,
    "message": "Students fetched successfully",
    "data": {
        "students": [...],
        "pagination": {
            "page": 1,
            "perPage": 10,
            "total": 150,
            "totalPages": 15
        }
    }
}
```

#### Get Single Student
```
GET /backend/api/students.php?action=single&id=STU001
```

#### Get Statistics
```
GET /backend/api/students.php?action=stats&class=10&section=A

Response:
{
    "success": true,
    "message": "Statistics fetched successfully",
    "data": {
        "total": 150,
        "active": 145,
        "male": 80,
        "female": 70,
        "newThisMonth": 5
    }
}
```

#### Create Student
```
POST /backend/api/students.php
Content-Type: application/json

{
    "name": "John Doe",
    "gender": "Male",
    "class": "10",
    "section": "A",
    "parent_name": "Mr. Doe",
    "contact": "+91 9876543210",
    "email": "john@example.com",
    "address": "123 Street, City",
    "dob": "2008-05-15",
    "joining_date": "2023-04-01",
    "blood_group": "A+",
    "photo": "data:image/jpeg;base64,...",
    "status": "Active"
}

Response:
{
    "success": true,
    "message": "Student created successfully",
    "data": {
        "id": "STU20241234567",
        "admission_no": "ADM20240123"
    }
}
```

#### Update Student
```
PUT /backend/api/students.php
Content-Type: application/json

{
    "id": "STU001",
    "name": "John Doe Updated",
    "contact": "+91 9876543211",
    ...
}
```

#### Delete Student
```
DELETE /backend/api/students.php?id=STU001
```

#### Bulk Delete Students
```
DELETE /backend/api/students.php?ids=STU001,STU002,STU003
```

#### Search Students
```
GET /backend/api/students.php?action=search&q=John
```

### Teachers API (`teachers.php`)

Similar structure to Students API:

- `GET ?action=list` - Get teachers list
- `GET ?action=single&id=TCH001` - Get single teacher
- `GET ?action=stats` - Get statistics
- `POST` - Create teacher
- `PUT` - Update teacher
- `DELETE ?id=TCH001` - Delete teacher

---

## 💻 Frontend Integration (jQuery)

### Using the App.js Module

The `frontend/assets/js/app.js` file provides a clean jQuery-based API client:

#### Example: Login
```javascript
EduManageApp.Auth.login(username, password, rememberMe,
    function(response) {
        // Success
        console.log('User:', response.data.user);
    },
    function(response) {
        // Error
        alert(response.message);
    }
);
```

#### Example: Get Students List
```javascript
EduManageApp.Students.getList(
    {
        page: 1,
        perPage: 10,
        class: '10',
        section: 'A',
        status: 'Active'
    },
    function(response) {
        // Success
        const students = response.data.students;
        // Render students in table
    },
    function(response) {
        // Error
        console.error(response.message);
    }
);
```

#### Example: Create Student
```javascript
const studentData = {
    name: $('#studentName').val(),
    gender: $('#studentGender').val(),
    class: $('#studentClass').val(),
    section: $('#studentSection').val(),
    parent_name: $('#parentName').val(),
    contact: $('#parentContact').val()
};

EduManageApp.Students.create(studentData,
    function(response) {
        // Success
        alert('Student created: ' + response.data.id);
        // Refresh table
    },
    function(response) {
        // Error
        alert('Error: ' + response.message);
    }
);
```

#### Example: Update Student
```javascript
studentData.id = 'STU001'; // Add the ID

EduManageApp.Students.update(studentData,
    function(response) {
        alert('Student updated successfully');
    },
    function(response) {
        alert('Error: ' + response.message);
    }
);
```

#### Example: Delete Student
```javascript
EduManageApp.Students.delete('STU001',
    function(response) {
        alert('Student deleted');
    },
    function(response) {
        alert('Error: ' + response.message);
    }
);
```

#### Example: Show Notification
```javascript
EduManageApp.showNotification('Operation successful!', 'success');
EduManageApp.showNotification('An error occurred', 'error');
EduManageApp.showNotification('Warning message', 'warning');
EduManageApp.showNotification('Information', 'info');
```

---

## 🔒 Security Features

1. **SQL Injection Prevention**: All queries use prepared statements with PDO
2. **Input Sanitization**: All user inputs are sanitized using `htmlspecialchars` and `strip_tags`
3. **XSS Prevention**: Output encoding for all user-generated content
4. **Session Management**: Secure session handling for authentication
5. **CORS Headers**: Configurable CORS for API access
6. **Error Logging**: Errors logged to file, not displayed to users
7. **Password Hashing**: Ready for bcrypt password hashing (currently plain text for demo)

### ⚠️ Production Security Checklist

Before deploying to production:

1. ✅ Change default passwords in database
2. ✅ Enable password hashing in auth.php:
   ```php
   // Replace
   if ($password !== $user['password'])
   // With
   if (!password_verify($password, $user['password']))
   ```
3. ✅ Update database credentials in `backend/config/db.php`
4. ✅ Set proper file permissions (644 for files, 755 for directories)
5. ✅ Enable HTTPS/SSL
6. ✅ Configure proper CORS headers for your domain
7. ✅ Enable PHP error logging, disable display_errors

---

## 📊 Database Schema Highlights

### Users Table
- Role-based access control (SuperAdmin, Admin, Teacher, Student, Parent)
- Permissions stored as JSON
- Last login tracking

### Students Table
- Complete student information
- Photo storage (base64)
- Status tracking (Active, Inactive, Graduated, Transferred)
- Documents stored in separate table

### Key Features
- Foreign key constraints for data integrity
- Indexed columns for performance
- Cascade deletes where appropriate
- Timestamps for all records

---

## 🎨 UI/UX Preservation

✅ **100% UI Unchanged**:
- All layouts, colors, fonts, spacing remain identical
- Responsive design preserved
- Animations and transitions unchanged
- Icons and graphics unchanged

✅ **Behavior Preserved**:
- Form validations work the same way
- Table sorting, filtering, pagination unchanged
- Modals and popups function identically
- User interactions remain consistent

---

## 🧩 Module Status

| Module | Backend API | Frontend Integration | Status |
|--------|-------------|---------------------|---------|
| Authentication | ✅ Complete | ✅ Complete | Ready |
| Students | ✅ Complete | ⚠️ Needs Integration | 90% Ready |
| Teachers | ✅ Complete | ⚠️ Needs Integration | 90% Ready |
| Attendance | ⚠️ Pending | ⚠️ Pending | 60% Ready |
| Exams | ⚠️ Pending | ⚠️ Pending | 60% Ready |
| Fees | ⚠️ Pending | ⚠️ Pending | 60% Ready |
| Library | ⚠️ Pending | ⚠️ Pending | 60% Ready |
| Transport | ⚠️ Pending | ⚠️ Pending | 60% Ready |
| Hostel | ⚠️ Pending | ⚠️ Pending | 60% Ready |

---

## 🔄 Migration from LocalStorage to Backend

### Current Pages Using LocalStorage

The following pages currently use `localStorage` for data persistence:
- `pages/students.html`
- `pages/teachers.html`
- `pages/attendance.html`
- `pages/exams.html`
- `pages/fees.html`
- `pages/library.html`
- `pages/transport.html`
- `pages/hostel.html`
- `pages/settings.html`

### How to Migrate a Page

**Example: Migrating Students Page**

1. **Include app.js** in the page:
```html
<script src="../assets/js/app.js"></script>
```

2. **Replace localStorage calls** with API calls:

**Before (LocalStorage)**:
```javascript
const students = JSON.parse(localStorage.getItem('edu_students') || '[]');
localStorage.setItem('edu_students', JSON.stringify(students));
```

**After (Backend API)**:
```javascript
// Get students
EduManageApp.Students.getList({page: 1, perPage: 10},
    function(response) {
        const students = response.data.students;
        // Render students
    }
);

// Save student
EduManageApp.Students.create(studentData,
    function(response) {
        alert('Student saved!');
    }
);
```

3. **Test the page** to ensure all functionality works

---

## 🐛 Troubleshooting

### Database Connection Error
**Problem**: "Database connection failed"

**Solution**:
- Check MySQL service is running
- Verify credentials in `backend/config/db.php`
- Ensure database `edumanage_pro` exists

### CORS Error
**Problem**: "Access to XMLHttpRequest blocked by CORS policy"

**Solution**:
- Verify CORS headers in `backend/config/db.php`
- Use same protocol (http/https) for frontend and backend
- Test with `Access-Control-Allow-Origin: *` first

### API Returns 404
**Problem**: API endpoints return 404 Not Found

**Solution**:
- Check file paths are correct
- Verify `.htaccess` if using Apache
- Check PHP error log for details

### Session Not Persisting
**Problem**: User logged out on page refresh

**Solution**:
- Uncomment session check in `index.html`
- Verify session is started in PHP backend
- Check browser cookies are enabled

---

## 📝 Development Guidelines

### Adding a New API Endpoint

1. Create new PHP file in `backend/api/`
2. Follow existing structure (auth.php, students.php)
3. Use helper functions from `functions.php`
4. Add endpoint to `app.js` modules
5. Test with Postman or browser

### Adding a New Page

1. Create HTML file in `frontend/pages/`
2. Use existing pages as template
3. Include app.js for API communication
4. Add menu item in `index.html` sidebar
5. Test navigation and functionality

---

## 👥 Default Users

| Role | Username | Password | Permissions |
|------|----------|----------|-------------|
| SuperAdmin | superadmin | super@123 | Full Access |
| Admin | admin | admin123 | All Modules |

---

## 📞 Support & Contact

For issues, questions, or support:
- **Email**: admin@edumanage.pro
- **GitHub Issues**: (if applicable)
- **Documentation**: This README

---

## 📄 License

Powered by **UpgradeNow Technologies**

---

## ✅ Checklist for Deployment

- [ ] Import database schema
- [ ] Configure database credentials
- [ ] Change default passwords
- [ ] Enable password hashing
- [ ] Test login functionality
- [ ] Test student CRUD operations
- [ ] Test teacher CRUD operations
- [ ] Configure CORS for production domain
- [ ] Set proper file permissions
- [ ] Enable HTTPS/SSL
- [ ] Test on production server
- [ ] Create database backups

---

**Thank you for using EduManage Pro! 🎓**
