<?php
/**
 * API Response Cache Helper
 * EduManage Pro - School Management System
 *
 * Simple file-based caching for API responses to reduce database load.
 * Especially useful for data that doesn't change frequently.
 */

class ApiCache {
    private static $instance = null;
    private $cacheDir;
    private $defaultTtl = 300; // 5 minutes default
    private $enabled = true;

    // Cache TTL settings by endpoint type (in seconds)
    private $ttlSettings = [
        'classes'       => 300,  // 5 minutes
        'sections'      => 300,  // 5 minutes
        'subjects'      => 300,  // 5 minutes
        'dropdowns'     => 600,  // 10 minutes
        'institution'   => 600,  // 10 minutes
        'stats'         => 60,   // 1 minute (more dynamic)
        'students_list' => 120,  // 2 minutes
        'teachers_list' => 120,  // 2 minutes
    ];

    private function __construct() {
        $this->cacheDir = __DIR__ . '/../../cache/api';

        // Create cache directory if not exists
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        // Check if cache directory is writable
        if (!is_writable($this->cacheDir)) {
            $this->enabled = false;
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate cache key from request parameters
     */
    public function generateKey($endpoint, $params = [], $schoolId = null) {
        $keyData = [
            'endpoint' => $endpoint,
            'params' => $params,
            'school_id' => $schoolId
        ];
        return md5(json_encode($keyData));
    }

    /**
     * Get cached data
     * @return mixed|null Returns cached data or null if not found/expired
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }

        $filePath = $this->cacheDir . '/' . $key . '.cache';

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false) {
            @unlink($filePath);
            return null;
        }

        // Check if expired
        if (isset($data['expires']) && time() > $data['expires']) {
            @unlink($filePath);
            return null;
        }

        return $data['value'] ?? null;
    }

    /**
     * Set cached data
     */
    public function set($key, $value, $ttl = null) {
        if (!$this->enabled) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $filePath = $this->cacheDir . '/' . $key . '.cache';

        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return @file_put_contents($filePath, serialize($data), LOCK_EX) !== false;
    }

    /**
     * Get TTL for endpoint type
     */
    public function getTtl($endpointType) {
        return $this->ttlSettings[$endpointType] ?? $this->defaultTtl;
    }

    /**
     * Delete specific cache entry
     */
    public function delete($key) {
        $filePath = $this->cacheDir . '/' . $key . '.cache';
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return true;
    }

    /**
     * Invalidate cache for specific endpoint pattern
     * Useful when data is updated
     */
    public function invalidate($pattern) {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return;
        }

        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Clear all cache
     */
    public function clear() {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    /**
     * Clean expired cache entries
     */
    public function cleanup() {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $files = glob($this->cacheDir . '/*.cache');
        $cleaned = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                @unlink($file);
                $cleaned++;
                continue;
            }

            $data = @unserialize($content);
            if ($data === false || (isset($data['expires']) && time() > $data['expires'])) {
                @unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Get cache statistics
     */
    public function getStats() {
        if (!is_dir($this->cacheDir)) {
            return ['enabled' => false, 'files' => 0, 'size' => 0];
        }

        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $content = @file_get_contents($file);
            if ($content) {
                $data = @unserialize($content);
                if ($data && isset($data['expires'])) {
                    if (time() > $data['expires']) {
                        $expiredCount++;
                    } else {
                        $validCount++;
                    }
                }
            }
        }

        return [
            'enabled' => $this->enabled,
            'total_files' => count($files),
            'valid_entries' => $validCount,
            'expired_entries' => $expiredCount,
            'total_size_bytes' => $totalSize,
            'total_size_kb' => round($totalSize / 1024, 2)
        ];
    }
}

/**
 * Helper function to get cache instance
 */
function getApiCache() {
    return ApiCache::getInstance();
}

/**
 * Helper function for cached API response
 * Use this wrapper in your API endpoints for automatic caching
 *
 * @param string $cacheKey Unique cache key
 * @param string $endpointType Type of endpoint for TTL lookup
 * @param callable $dataFetcher Function that returns the data to cache
 * @return mixed Cached or fresh data
 */
function getCachedResponse($cacheKey, $endpointType, callable $dataFetcher) {
    $cache = getApiCache();

    // Try to get from cache
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Fetch fresh data
    $data = $dataFetcher();

    // Cache the result
    $ttl = $cache->getTtl($endpointType);
    $cache->set($cacheKey, $data, $ttl);

    return $data;
}
?>
