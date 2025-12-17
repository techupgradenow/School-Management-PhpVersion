# 🚀 How to Run the School Management System

## 📋 Prerequisites

Before running the application, ensure you have:

1. ✅ **XAMPP/WAMP/MAMP** installed (includes Apache, MySQL, PHP)
2. ✅ **Web browser** (Chrome, Firefox, Edge, Safari)
3. ✅ **Text editor** (VS Code, Sublime, Notepad++)

---

## 🛠️ OPTION 1: Run with XAMPP (Recommended)

### Step 1: Install XAMPP
Download from: https://www.apachefriends.org/download.html

### Step 2: Move Project to XAMPP Directory
```bash
# Move/Copy project folder to:
C:\xampp\htdocs\School-Management-PhpVersion
```

**OR** create a symbolic link:
```bash
# Run Command Prompt as Administrator
mklink /D "C:\xampp\htdocs\School-Management-PhpVersion" "C:\Users\admin\Documents\GitHub\School-Management-PhpVersion"
```

### Step 3: Start XAMPP Services
1. Open **XAMPP Control Panel**
2. Click **Start** for **Apache**
3. Click **Start** for **MySQL**
4. Wait for both to show green "Running" status

### Step 4: Create Database
1. Open browser: http://localhost/phpmyadmin
2. Click **"New"** in sidebar
3. Database name: `edumanage_pro`
4. Collation: `utf8mb4_unicode_ci`
5. Click **"Create"**

### Step 5: Import Schema
1. Select `edumanage_pro` database
2. Click **"Import"** tab
3. Click **"Choose File"**
4. Navigate to: `C:\Users\admin\Documents\GitHub\School-Management-PhpVersion\database\schema.sql`
5. Click **"Go"**
6. Wait for success message

### Step 6: Open Application
Open browser and navigate to:
```
http://localhost/School-Management-PhpVersion/frontend/index.html
```

### Step 7: Login
```
Username: admin
Password: admin123
```

---

## 🛠️ OPTION 2: Run with PHP Built-in Server

### Step 1: Check PHP Installation
Open Command Prompt:
```bash
php --version
```

If PHP is not found, install it:
- Download PHP: https://windows.php.net/download/
- Extract to `C:\php`
- Add `C:\php` to system PATH

### Step 2: Navigate to Project
```bash
cd C:\Users\admin\Documents\GitHub\School-Management-PhpVersion
```

### Step 3: Start PHP Server
```bash
php -S localhost:8000
```

You should see:
```
PHP 8.x.x Development Server started at http://localhost:8000
```

### Step 4: Start MySQL
Ensure MySQL is running via:
- XAMPP MySQL service
- OR standalone MySQL installation

### Step 5: Create Database & Import Schema
Follow steps 4-5 from OPTION 1 above.

### Step 6: Open Application
```
http://localhost:8000/frontend/index.html
```

---

## 🛠️ OPTION 3: Run with WAMP

### Step 1: Install WAMP
Download from: http://www.wampserver.com/en/

### Step 2: Move Project
```bash
# Move to WAMP www directory:
C:\wamp64\www\School-Management-PhpVersion
```

### Step 3: Start WAMP
1. Open WAMP
2. Wait for icon to turn green
3. Left-click WAMP icon → MySQL → phpMyAdmin

### Step 4: Create Database & Import
Follow steps 4-5 from OPTION 1.

### Step 5: Open Application
```
http://localhost/School-Management-PhpVersion/frontend/index.html
```

---

## 🔧 TROUBLESHOOTING

### Issue 1: "XAMPP Not Starting"
**Solution:**
1. Check if port 80 is in use:
   ```bash
   netstat -ano | findstr :80
   ```
2. Stop Skype/IIS if using port 80
3. OR change Apache port in `C:\xampp\apache\conf\httpd.conf`:
   ```
   Listen 8080
   ```
   Then access: `http://localhost:8080/...`

### Issue 2: "Database Connection Failed"
**Error:** `SQLSTATE[HY000] [1049] Unknown database`

**Solution:**
1. Database not created. Go to phpMyAdmin and create `edumanage_pro`
2. Check database name in `backend/config/db.php` (line 11):
   ```php
   define('DB_NAME', 'edumanage_pro');
   ```

### Issue 3: "Access Denied for User 'root'"
**Error:** `SQLSTATE[HY000] [1045] Access denied`

**Solution:**
Update `backend/config/db.php`:
```php
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty for XAMPP default
```

For custom MySQL:
```php
define('DB_PASS', 'your_mysql_password');
```

### Issue 4: "404 Not Found"
**Solution:**
Check project path matches URL:
```
URL: http://localhost/School-Management-PhpVersion/frontend/index.html
Path: C:\xampp\htdocs\School-Management-PhpVersion\frontend\index.html
```

### Issue 5: "API Not Working" (Network errors in browser)
**Solution:**
1. Open Browser DevTools (F12)
2. Check Console for errors
3. Check Network tab for failed requests
4. Verify API path in `frontend/assets/js/app.js`:
   ```javascript
   const API_BASE_URL = '../backend/api';
   ```

### Issue 6: "Blank Page / No Errors"
**Solution:**
1. Check Browser Console (F12)
2. Look for JavaScript errors
3. Ensure jQuery is loaded
4. Check if app.js is included

---

## ✅ VERIFY INSTALLATION

### Test 1: Check PHP
```bash
php -v
```
Expected: `PHP 8.x.x`

### Test 2: Check MySQL
```bash
mysql -u root -p
```
Enter password (blank for XAMPP default)

### Test 3: Check Database
```sql
SHOW DATABASES;
USE edumanage_pro;
SHOW TABLES;
```
Expected: 25+ tables listed

### Test 4: Check API Directly
Open browser:
```
http://localhost/School-Management-PhpVersion/backend/api/students.php?action=list
```

Expected Response:
```json
{
  "success": true,
  "message": "Students fetched successfully",
  "data": {
    "students": [],
    "pagination": {...}
  }
}
```

### Test 5: Check Frontend
```
http://localhost/School-Management-PhpVersion/frontend/index.html
```
Expected: Login page displays

---

## 📊 DEFAULT CREDENTIALS

After importing schema, use these credentials:

### Admin Account:
```
Username: admin
Password: admin123
Role: Administrator
```

### Teacher Account:
```
Username: teacher
Password: teacher123
Role: Teacher
```

### Accountant Account:
```
Username: accountant
Password: acc123
Role: Accountant
```

**⚠️ IMPORTANT:** Change these passwords in production!

---

## 🎯 QUICK START CHECKLIST

- [ ] XAMPP/WAMP installed
- [ ] Apache started (green)
- [ ] MySQL started (green)
- [ ] Database `edumanage_pro` created
- [ ] Schema imported successfully
- [ ] Project accessible at http://localhost/School-Management-PhpVersion/
- [ ] Login page loads
- [ ] Login with admin/admin123 works
- [ ] Students page opens

---

## 📱 ACCESS FROM MOBILE/OTHER DEVICES

### Step 1: Find Your Computer's IP
```bash
ipconfig
```
Look for: `IPv4 Address: 192.168.x.x`

### Step 2: Configure XAMPP
Edit `C:\xampp\apache\conf\extra\httpd-xampp.conf`:

Find:
```apache
<Directory "C:/xampp/htdocs">
    Require local
```

Change to:
```apache
<Directory "C:/xampp/htdocs">
    Require all granted
```

Restart Apache.

### Step 3: Access from Mobile
On mobile browser:
```
http://192.168.x.x/School-Management-PhpVersion/frontend/index.html
```
(Replace x.x with your IP)

---

## 🔒 PRODUCTION DEPLOYMENT

Before deploying to production server:

1. **Enable Password Hashing:**
   See `backend/helpers/functions.php` comments

2. **Update Database Credentials:**
   Edit `backend/config/db.php` with production credentials

3. **Configure CORS:**
   Update allowed origins in `backend/config/db.php`

4. **Enable HTTPS:**
   Install SSL certificate

5. **Set Error Reporting:**
   ```php
   ini_set('display_errors', 0);
   error_reporting(0);
   ```

6. **Database Backup:**
   ```bash
   mysqldump -u root -p edumanage_pro > backup.sql
   ```

---

## 📞 SUPPORT

If you encounter issues:

1. Check Browser Console (F12)
2. Check PHP Error Log: `C:\xampp\apache\logs\error.log`
3. Check MySQL Error Log
4. Review documentation:
   - DATABASE_SETUP.md
   - QUICKSTART.md
   - INTEGRATION_SUMMARY.md

---

## 🎉 SUCCESS!

If you can:
- ✅ See login page
- ✅ Login with admin credentials
- ✅ Navigate to Students page
- ✅ See students list loading
- ✅ Create a new student
- ✅ See student in database

**Your application is running successfully! 🚀**

---

**Powered by UpgradeNow Technologies**
