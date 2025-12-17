# EduManage Pro - Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Setup Database (2 minutes)

1. Open **phpMyAdmin** or MySQL command line
2. Run the database setup:

```sql
-- Option A: Import the schema file
SOURCE /path/to/database/schema.sql;

-- Option B: Copy and paste from schema.sql file
```

3. The database `edumanage_pro` will be created with:
   - 25+ tables
   - 2 default users (superadmin, admin)
   - Complete schema for all modules

### Step 2: Configure Backend (1 minute)

Edit `backend/config/db.php`:

```php
define('DB_HOST', 'localhost');        // Your host
define('DB_USER', 'root');             // Your username
define('DB_PASS', '');                 // Your password
define('DB_NAME', 'edumanage_pro');    // Database name
```

### Step 3: Start Application (1 minute)

1. Start your web server (Apache/Nginx)
2. Navigate to:
   ```
   http://localhost/edumanage-pro/frontend/
   ```

### Step 4: Login (30 seconds)

Use default credentials:
- **Username**: `admin`
- **Password**: `admin123`

### Step 5: Test Features (30 seconds)

1. Click on **Students** in sidebar
2. The page will load (currently uses localStorage)
3. To connect to backend API, see integration examples below

---

## 🔌 Quick API Integration Example

### Replace LocalStorage with Backend API

**Current Code (LocalStorage):**
```javascript
// Get students
const students = JSON.parse(localStorage.getItem('edu_students') || '[]');

// Save student
students.push(newStudent);
localStorage.setItem('edu_students', JSON.stringify(students));
```

**New Code (Backend API with jQuery):**
```javascript
// Get students
EduManageApp.Students.getList({page: 1, perPage: 10},
    function(response) {
        const students = response.data.students;
        // Render students in your table
    }
);

// Save student
EduManageApp.Students.create(studentData,
    function(response) {
        EduManageApp.showNotification('Student saved!', 'success');
        // Refresh table
    }
);
```

---

## 📝 Test API Endpoints

### Test Login API

**Using Browser Console:**
```javascript
$.ajax({
    url: '../backend/api/auth.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
        action: 'login',
        username: 'admin',
        password: 'admin123'
    }),
    success: function(response) {
        console.log('Login Success:', response);
    }
});
```

### Test Students API

**Get Students List:**
```javascript
$.ajax({
    url: '../backend/api/students.php?action=list&page=1&perPage=10',
    type: 'GET',
    success: function(response) {
        console.log('Students:', response.data.students);
    }
});
```

**Create Student:**
```javascript
$.ajax({
    url: '../backend/api/students.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
        name: 'Test Student',
        gender: 'Male',
        class: '10',
        section: 'A',
        parent_name: 'Test Parent',
        contact: '+91 9876543210',
        status: 'Active'
    }),
    success: function(response) {
        console.log('Student Created:', response);
    }
});
```

---

## 🎯 Next Steps

### For Full Integration:

1. **Open any page** (e.g., `frontend/pages/students.html`)
2. **Include app.js**:
   ```html
   <script src="../assets/js/app.js"></script>
   ```
3. **Replace localStorage calls** with `EduManageApp` API calls
4. **Test the page** thoroughly

### Module Priority:

1. ✅ **Login** - Already integrated in index.html
2. ⚠️ **Students** - Backend ready, needs frontend integration
3. ⚠️ **Teachers** - Backend ready, needs frontend integration
4. ⏳ **Attendance** - Backend pending
5. ⏳ **Exams** - Backend pending
6. ⏳ **Fees** - Backend pending

---

## 🐛 Common Issues & Solutions

### Issue: "Database connection failed"
**Solution**: Check MySQL is running and credentials in `db.php` are correct

### Issue: "CORS policy" error
**Solution**: Ensure frontend and backend are on same domain/port

### Issue: API returns empty response
**Solution**: Check PHP error log in `backend/config/error.log`

### Issue: Session not persisting
**Solution**: Uncomment session check in `index.html` (line ~370)

---

## 📚 Resources

- **Full Documentation**: See `README.md`
- **API Reference**: See `README.md` → API Documentation section
- **Database Schema**: See `database/schema.sql`
- **Helper Functions**: See `backend/helpers/functions.php`

---

## ✅ Verification Checklist

- [ ] Database imported successfully
- [ ] Can access `http://localhost/edumanage-pro/frontend/`
- [ ] Can login with admin/admin123
- [ ] Dashboard loads correctly
- [ ] Can navigate between pages
- [ ] Browser console shows no errors

---

## 🎉 Success!

If you can login and see the dashboard, you're all set!

Now you can:
- Integrate remaining pages with backend API
- Add more API endpoints as needed
- Customize the application for your needs

**Need help?** Check README.md for detailed documentation.

---

**Powered by UpgradeNow Technologies** 🚀
