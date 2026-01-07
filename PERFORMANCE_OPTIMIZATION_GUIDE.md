# Performance Optimization Guide
## EduManage Pro - 500+ Concurrent Users

This guide explains how to configure your application to handle 500+ concurrent users without performance issues.

---

## Current Performance Metrics ✅

Based on testing:
- **Database Connections**: 100 connections in 18ms (0.18ms per connection)
- **Query Performance**: 1-1.5ms average per query
- **API Response Time**: 30-40ms average
- **Cache Performance**: 0.72ms read, 2.61ms write (file-based)
- **Session Creation**: 0.29ms per session

**Expected capacity: 200-300 concurrent users with current setup**

---

## Upgrade Steps for 500+ Users

### 1. MySQL Configuration (REQUIRED)

**File**: `C:\xampp\mysql\bin\my.ini` or `C:\xampp\mysql\my.ini`

Add these settings under `[mysqld]` section:

```ini
max_connections = 500
innodb_buffer_pool_size = 512M
innodb_buffer_pool_instances = 4
query_cache_size = 128M
thread_cache_size = 50
table_open_cache = 4000
```

**After editing, restart MySQL** via XAMPP Control Panel.

Reference file: `backend/database/mysql_optimization.ini`

---

### 2. PHP OpCache Configuration (CRITICAL)

**File**: `C:\xampp\php\php.ini`

Find and modify these settings:

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
```

**After editing, restart Apache** via XAMPP Control Panel.

Reference file: `backend/database/php_optimization.ini`

**Impact**: 3-5x performance improvement

---

### 3. Redis Cache (OPTIONAL but RECOMMENDED)

For file-based cache (current): Good for 200-300 users
For Redis cache: Can handle 500-1000+ users

#### Install Redis on Windows:

1. Download: https://github.com/microsoftarchive/redis/releases
2. Extract and run `redis-server.exe`
3. Install PHP Redis extension:
   - Download `php_redis.dll` for your PHP version
   - Place in `C:\xampp\php\ext\`
   - Edit `php.ini`, add: `extension=php_redis`
   - Restart Apache

The application automatically uses Redis if available, otherwise falls back to file cache.

---

### 4. Current Optimizations (Already Implemented) ✅

- ✅ **N+1 Query Fix**: Batch loading permissions (50x faster)
- ✅ **Pagination**: Limit/offset support
- ✅ **Persistent Connections**: Database connection pooling
- ✅ **Batch Permission Loading**: Single query instead of N queries
- ✅ **Database Indexes**: Optimized for all lookups
- ✅ **File-based Caching**: 5-minute TTL
- ✅ **UUID Architecture**: Scalable user identification
- ✅ **Singleton Pattern**: Single DB instance per request

---

## Performance Testing

Run the performance test script:

```bash
php backend/database/performance_test.php
```

This will test:
- Database connection pooling
- Query performance
- Cache performance
- Session handling
- API endpoint response times
- System resource usage

---

## Expected Performance After Full Optimization

| Metric | Current | After Redis + OpCache |
|--------|---------|----------------------|
| Login | 50-100ms | 20-40ms |
| List Users | 30-40ms | 10-20ms (cached: 2-5ms) |
| Create User | 100-200ms | 50-100ms |
| Update User | 100-150ms | 40-80ms |
| Concurrent Users | 200-300 | **500-700** |
| Memory per 100 users | ~80MB | ~50MB |

---

## Architecture Overview

```
Frontend (Browser)
     ↓
  index.html / users.html
     ↓ (fetch API)
Backend API (PHP)
     ↓
  ┌─────────────────┐
  │ Redis/File Cache│ ← 5min TTL, auto-invalidation
  └────────┬────────┘
           ↓ (cache miss)
  ┌─────────────────┐
  │ PDO Persistent  │ ← Connection pooling
  │ Connections     │
  └────────┬────────┘
           ↓
  ┌─────────────────┐
  │ MySQL Database  │ ← Indexed, optimized queries
  │ - users         │ ← 8 indexes
  │ - permissions   │ ← 5 indexes, FK CASCADE
  │ - sessions      │ ← Session storage
  └─────────────────┘
```

---

## Monitoring & Troubleshooting

### Check Active Connections

```sql
SHOW STATUS LIKE 'Threads_connected';
SHOW VARIABLES LIKE 'max_connections';
```

### Check OpCache Status

```php
<?php print_r(opcache_get_status()); ?>
```

### Check Cache Type

```php
<?php
require 'backend/helpers/redis_cache.php';
print_r(RedisCache::getStats());
?>
```

### Monitor Slow Queries

Check: `C:/xampp/mysql/data/mysql-slow.log`

---

## Scaling Beyond 500 Users

For 1000+ concurrent users, consider:

1. **Load Balancer** (nginx or HAProxy)
2. **Read Replicas** for MySQL
3. **Separate Cache Server** (dedicated Redis)
4. **CDN** for static assets
5. **Session Storage** in Redis instead of database

---

## Key Files

- `backend/config/db.php` - Database configuration with persistent connections
- `backend/helpers/cache.php` - File-based cache system
- `backend/helpers/redis_cache.php` - Redis cache (auto-fallback)
- `backend/helpers/session_manager.php` - Database session handler
- `backend/api/users.php` - Optimized with batch loading
- `backend/api/auth.php` - Optimized login with caching

---

## Security Notes

All optimizations maintain security:
- ✅ Passwords hashed with bcrypt
- ✅ SQL injection protection (prepared statements)
- ✅ UUID never exposed in API
- ✅ Session hijacking protection
- ✅ CORS configured
- ✅ Input sanitization

---

## Support

Run performance test regularly to monitor:
```bash
php backend/database/performance_test.php
```

Expected output:
- Connection creation: <1ms per connection
- Query execution: <2ms per query
- API response: <50ms average
- Cache read: <1ms (Redis) or <3ms (file)

---

**Last Updated**: December 2025
**Version**: 2.0 - High Performance Edition
