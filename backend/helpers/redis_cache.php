<?php
/**
 * Redis Cache System (Alternative to File Cache)
 * EduManage Pro - School Management System
 *
 * High-performance caching for 500+ concurrent users
 * Falls back to file cache if Redis is not available
 *
 * Install Redis on Windows:
 * 1. Download: https://github.com/microsoftarchive/redis/releases
 * 2. Extract and run: redis-server.exe
 * 3. Install PHP Redis extension: php_redis.dll
 */

class RedisCache {
    private static $redis = null;
    private static $useRedis = false;
    private static $prefix = 'edumanage:';
    private static $defaultTTL = 300; // 5 minutes

    /**
     * Initialize Redis connection
     */
    public static function init() {
        if (self::$redis !== null) {
            return self::$useRedis;
        }

        // Try to connect to Redis
        if (class_exists('Redis')) {
            try {
                self::$redis = new Redis();
                self::$useRedis = self::$redis->connect('127.0.0.1', 6379, 2);

                if (self::$useRedis) {
                    // Test connection
                    self::$redis->ping();
                    error_log("Redis cache initialized successfully");
                }
            } catch (Exception $e) {
                self::$useRedis = false;
                error_log("Redis connection failed: " . $e->getMessage());
            }
        }

        // Fallback to file cache if Redis not available
        if (!self::$useRedis) {
            require_once __DIR__ . '/cache.php';
            error_log("Using file-based cache (Redis not available)");
        }

        return self::$useRedis;
    }

    /**
     * Get cached value
     */
    public static function get($key) {
        self::init();

        if (self::$useRedis) {
            $value = self::$redis->get(self::$prefix . $key);
            return $value === false ? null : json_decode($value, true);
        } else {
            return Cache::get($key);
        }
    }

    /**
     * Set cached value
     */
    public static function set($key, $value, $ttl = null) {
        self::init();
        $ttl = $ttl ?? self::$defaultTTL;

        if (self::$useRedis) {
            return self::$redis->setex(
                self::$prefix . $key,
                $ttl,
                json_encode($value)
            );
        } else {
            return Cache::set($key, $value, $ttl);
        }
    }

    /**
     * Delete cached value
     */
    public static function delete($key) {
        self::init();

        if (self::$useRedis) {
            return self::$redis->del(self::$prefix . $key);
        } else {
            return Cache::delete($key);
        }
    }

    /**
     * Clear all cache or by pattern
     */
    public static function clear($pattern = null) {
        self::init();

        if (self::$useRedis) {
            if ($pattern) {
                $keys = self::$redis->keys(self::$prefix . $pattern . '*');
                if (!empty($keys)) {
                    return self::$redis->del($keys);
                }
            } else {
                return self::$redis->flushDB();
            }
        } else {
            return Cache::clear($pattern);
        }
    }

    /**
     * Invalidate user-related caches
     */
    public static function invalidateUser($userId) {
        self::delete("user_{$userId}");
        self::delete("users_list");
        self::delete("users_stats");
    }

    /**
     * Get or set cached value with callback
     */
    public static function remember($key, $callback, $ttl = null) {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Increment a value (atomic operation)
     */
    public static function increment($key, $amount = 1) {
        self::init();

        if (self::$useRedis) {
            return self::$redis->incrBy(self::$prefix . $key, $amount);
        } else {
            $value = (int)self::get($key) + $amount;
            self::set($key, $value, 3600);
            return $value;
        }
    }

    /**
     * Get cache statistics
     */
    public static function getStats() {
        self::init();

        if (self::$useRedis) {
            return [
                'type' => 'Redis',
                'connected' => true,
                'info' => self::$redis->info()
            ];
        } else {
            return [
                'type' => 'File',
                'connected' => true
            ];
        }
    }
}
?>
