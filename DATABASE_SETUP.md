# 🗄️ Database Setup Guide

## Quick Database Configuration

### Current Configuration
**File:** `backend/config/db.php`

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'edumanage_pro');
```

---

## 🚀 QUICK SETUP (3 Steps)

### Step 1: Create Database
```sql
CREATE DATABASE edumanage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Via phpMyAdmin:**
1. Open http://localhost/phpmyadmin
2. Click "New" in sidebar
3. Database name: `edumanage_pro`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

**Via Command Line:**
```bash
mysql -u root -p
CREATE DATABASE edumanage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### Step 2: Import Schema
```bash
# Using command line
mysql -u root -p edumanage_pro < database/schema.sql

# OR copy path and use in MySQL Workbench/phpMyAdmin import
```

**Via phpMyAdmin:**
1. Select `edumanage_pro` database
2. Click "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"

### Step 3: Verify Installation
```sql
USE edumanage_pro;
SHOW TABLES;
```

**Expected Output (25+ tables):**
```
+---------------------------+
| Tables_in_edumanage_pro   |
+---------------------------+
| activity_logs             |
| attendance                |
| classes                   |
| exam_marks                |
| exams                     |
| fee_payments              |
| fee_structures            |
| hostel_allocations        |
| hostel_blocks             |
| hostel_rooms              |
| library_books             |
| library_issues            |
| notifications             |
| student_documents         |
| students                  |
| subject_master            |
| teachers                  |
| transport_assignments     |
| transport_routes          |
| transport_stops           |
| users                     |
| ...                       |
+---------------------------+
```

---

## ✅ TEST DATABASE CONNECTION

### Method 1: Direct API Test
Open browser:
```
http://localhost/School-Management-PhpVersion/backend/api/students.php?action=list
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Students fetched successfully",
  "data": {
    "students": [],
    "pagination": {
      "page": 1,
      "perPage": 10,
      "total": 0,
      "totalPages": 0
    }
  }
}
```

**If you see this** = ✅ Database connected!

### Method 2: Create Test Connection Script
Create: `test_connection.php`
```php
<?php
require_once 'backend/config/db.php';

try {
    $db = getDB();
    echo "✅ Database connection successful!\n";

    // Test query
    $stmt = $db->query("SELECT COUNT(*) as count FROM students");
    $result = $stmt->fetch();
    echo "✅ Students table accessible\n";
    echo "Total students: " . $result['count'] . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
```

Run:
```bash
php test_connection.php
```

---

## 🔧 TROUBLESHOOTING

### Issue 1: Database doesn't exist
```
Error: SQLSTATE[HY000] [1049] Unknown database 'edumanage_pro'
```

**Solution:**
```sql
CREATE DATABASE edumanage_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Issue 2: Access denied
```
Error: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'
```

**Solution:** Update `backend/config/db.php` with correct credentials:
```php
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
```

### Issue 3: Tables don't exist
```
Error: SQLSTATE[42S02]: Base table or view not found
```

**Solution:** Import schema:
```bash
mysql -u root -p edumanage_pro < database/schema.sql
```

### Issue 4: PHP can't connect to MySQL
```
Error: could not find driver
```

**Solution:** Enable PHP MySQL extension:

**XAMPP/WAMP:** Already enabled

**Custom PHP:** Edit `php.ini`:
```ini
extension=pdo_mysql
extension=mysqli
```

Restart Apache/PHP-FPM.

---

## 📊 DEFAULT DATA

The schema includes default data:

### Default Users (users table):
```sql
-- Admin User
username: admin
password: admin123 (plain text - change in production!)
role: admin

-- Teacher User
username: teacher
password: teacher123
role: teacher

-- Accountant User
username: accountant
password: acc123
role: accountant
```

### Default Classes:
- Class 1 to Class 12
- Sections: A, B, C, Science, Commerce, Arts

### Default Subjects:
- Mathematics, Science, English, Hindi, Social Studies, etc.

---

## 🔒 SECURITY CHECKLIST

Before production:

- [ ] Change default passwords
- [ ] Enable password hashing (see README.md)
- [ ] Update database credentials
- [ ] Restrict database user permissions
- [ ] Enable SSL for database connection
- [ ] Set proper file permissions
- [ ] Configure firewall rules
- [ ] Regular database backups

---

## 💾 BACKUP DATABASE

### Manual Backup:
```bash
# Export all data
mysqldump -u root -p edumanage_pro > backup_$(date +%Y%m%d).sql

# Export structure only
mysqldump -u root -p --no-data edumanage_pro > structure_backup.sql
```

### Restore from Backup:
```bash
mysql -u root -p edumanage_pro < backup_20250115.sql
```

---

## 📈 DATABASE OPTIMIZATION

### Add Indexes (if needed):
```sql
-- Already included in schema.sql, but for reference:
CREATE INDEX idx_student_class ON students(class);
CREATE INDEX idx_student_status ON students(status);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_fee_payments_student ON fee_payments(student_id);
```

### Check Table Status:
```sql
SHOW TABLE STATUS;
```

### Optimize Tables:
```sql
OPTIMIZE TABLE students, teachers, attendance, exams;
```

---

## 🎯 NEXT STEPS AFTER DATABASE SETUP

1. ✅ Database created and schema imported
2. ✅ Test connection successful
3. ✅ Default data available
4. ⏳ Test login: http://localhost/School-Management-PhpVersion/frontend/index.html
5. ⏳ Test Students API integration
6. ⏳ Integrate remaining modules

---

## 📞 COMMON SQL QUERIES

### Check Student Count:
```sql
SELECT COUNT(*) as total FROM students;
SELECT COUNT(*) as active FROM students WHERE status = 'Active';
```

### View Recent Activity:
```sql
SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10;
```

### Check All Tables Size:
```sql
SELECT
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = 'edumanage_pro'
ORDER BY (data_length + index_length) DESC;
```

### Reset Auto-increment:
```sql
ALTER TABLE students AUTO_INCREMENT = 1;
```

---

**Powered by UpgradeNow Technologies**
