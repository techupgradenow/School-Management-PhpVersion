<?php
/**
 * Simple File-Based Cache System
 * EduManage Pro - School Management System
 *
 * Provides caching for frequently accessed data to reduce database load
 * Suitable for 100+ concurrent users
 */

class Cache {
    private static $cacheDir;
    private static $defaultTTL = 300; // 5 minutes default

    /**
     * Initialize cache directory
     */
    public static function init() {
        self::$cacheDir = __DIR__ . '/../../cache/';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Get cached value
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public static function get($key) {
        self::init();
        $file = self::getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        if (!$data || !isset($data['expires']) || !isset($data['value'])) {
            return null;
        }

        // Check if expired
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cached value
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     */
    public static function set($key, $value, $ttl = null) {
        self::init();
        $file = self::getFilePath($key);

        $data = [
            'expires' => time() + ($ttl ?? self::$defaultTTL),
            'value' => $value,
            'created' => time()
        ];

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Delete cached value
     * @param string $key Cache key
     */
    public static function delete($key) {
        self::init();
        $file = self::getFilePath($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all cache or by prefix
     * @param string|null $prefix Optional prefix to clear specific keys
     */
    public static function clear($prefix = null) {
        self::init();

        $files = glob(self::$cacheDir . '*.cache');
        foreach ($files as $file) {
            if ($prefix) {
                $filename = basename($file, '.cache');
                if (strpos($filename, md5($prefix)) === 0) {
                    unlink($file);
                }
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Invalidate user-related caches when user data changes
     * @param string $userId User ID
     */
    public static function invalidateUser($userId) {
        self::delete("user_{$userId}");
        self::delete("users_list");
        self::delete("users_stats");
    }

    /**
     * Generate file path for cache key
     */
    private static function getFilePath($key) {
        return self::$cacheDir . md5($key) . '.cache';
    }

    /**
     * Get or set cached value with callback
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated value
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
}

// Auto-cleanup expired cache files (1% chance per request)
if (rand(1, 100) === 1) {
    $cacheDir = __DIR__ . '/../../cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*.cache');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['expires']) && $data['expires'] < time()) {
                unlink($file);
            }
        }
    }
}
?>
