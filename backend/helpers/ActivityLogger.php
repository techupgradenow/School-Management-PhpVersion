<?php
/**
 * =====================================================
 * ACTIVITY LOGGER CLASS
 * Centralized history tracking for all modules
 * =====================================================
 *
 * Usage:
 *   $logger = new ActivityLogger($pdo);
 *   $logger->log('students', 'STU001', 'ADD', null, $newData, 'Added new student');
 *
 * With Transaction:
 *   $logger->logWithTransaction('students', 'STU001', 'UPDATE', $oldData, $newData, function($pdo) {
 *       // Your database operations here
 *       return true; // or false to rollback
 *   });
 */

class ActivityLogger
{
    private $pdo;
    private $currentUser = null;
    private $userRole = null;
    private $userName = null;

    /**
     * Constructor
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadCurrentUser();
    }

    /**
     * Load current user from session
     */
    private function loadCurrentUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->currentUser = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
        $this->userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Unknown';
        $this->userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'Unknown';
    }

    /**
     * Set user context manually (useful for API/CLI operations)
     */
    public function setUserContext($userId, $userName, $userRole)
    {
        $this->currentUser = $userId;
        $this->userName = $userName;
        $this->userRole = $userRole;
        return $this;
    }

    /**
     * Get client IP address
     */
    private function getClientIP()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Get user agent string
     */
    private function getUserAgent()
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);
    }

    /**
     * Get request URL
     */
    private function getRequestUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return substr("{$protocol}://{$host}{$uri}", 0, 500);
    }

    /**
     * Get request method
     */
    private function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    }

    /**
     * Get session ID
     */
    private function getSessionId()
    {
        return session_id() ?: null;
    }

    /**
     * Calculate changed fields between old and new data
     */
    public function getChangedFields($oldData, $newData)
    {
        if (empty($oldData) || empty($newData)) {
            return null;
        }

        $old = is_array($oldData) ? $oldData : json_decode($oldData, true);
        $new = is_array($newData) ? $newData : json_decode($newData, true);

        if (!is_array($old) || !is_array($new)) {
            return null;
        }

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            // Skip internal/meta fields
            if (in_array($key, ['updated_at', 'created_at', 'password', 'password_hash'])) {
                continue;
            }

            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $key,
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }

    /**
     * Generate human-readable description
     */
    private function generateDescription($module, $entityName, $action, $changedFields = null)
    {
        $entityDisplay = $entityName ? "'{$entityName}'" : 'record';
        $moduleDisplay = ucfirst(str_replace('_', ' ', $module));

        switch ($action) {
            case 'ADD':
                return "Added new {$moduleDisplay}: {$entityDisplay}";
            case 'UPDATE':
                $fieldCount = is_array($changedFields) ? count($changedFields) : 0;
                $fieldText = $fieldCount > 0 ? " ({$fieldCount} field(s) changed)" : "";
                return "Updated {$moduleDisplay}: {$entityDisplay}{$fieldText}";
            case 'DELETE':
                return "Deleted {$moduleDisplay}: {$entityDisplay}";
            case 'RESTORE':
                return "Restored {$moduleDisplay}: {$entityDisplay}";
            case 'LOGIN':
                return "User logged in: {$entityDisplay}";
            case 'LOGOUT':
                return "User logged out: {$entityDisplay}";
            case 'EXPORT':
                return "Exported {$moduleDisplay} data";
            case 'IMPORT':
                return "Imported {$moduleDisplay} data";
            default:
                return "{$action} on {$moduleDisplay}: {$entityDisplay}";
        }
    }

    /**
     * Sanitize data for logging (remove sensitive fields)
     */
    private function sanitizeData($data)
    {
        if (empty($data)) {
            return null;
        }

        $arr = is_array($data) ? $data : json_decode($data, true);

        if (!is_array($arr)) {
            return $data;
        }

        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_hash', 'token', 'secret', 'api_key', 'credit_card'];
        foreach ($sensitiveFields as $field) {
            if (isset($arr[$field])) {
                $arr[$field] = '[REDACTED]';
            }
        }

        return json_encode($arr, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Main logging function
     *
     * @param string $module Module name (students, teachers, etc.)
     * @param string $entityId Primary key of the affected record
     * @param string $action Action type (ADD, UPDATE, DELETE, etc.)
     * @param mixed $oldData Data before change (array or JSON)
     * @param mixed $newData Data after change (array or JSON)
     * @param string|null $description Custom description (auto-generated if null)
     * @param string|null $entityName Human-readable name of the entity
     * @param string $status SUCCESS, FAILED, or PENDING
     * @param string|null $errorMessage Error details if status is FAILED
     * @return int|false Returns inserted ID or false on failure
     */
    public function log(
        $module,
        $entityId,
        $action,
        $oldData = null,
        $newData = null,
        $description = null,
        $entityName = null,
        $status = 'SUCCESS',
        $errorMessage = null
    ) {
        try {
            // Prepare data
            $oldDataJson = $this->sanitizeData($oldData);
            $newDataJson = $this->sanitizeData($newData);
            $changedFields = ($action === 'UPDATE') ? $this->getChangedFields($oldData, $newData) : null;
            $changedFieldsJson = $changedFields ? json_encode($changedFields, JSON_UNESCAPED_UNICODE) : null;

            // Auto-generate description if not provided
            if (empty($description)) {
                $description = $this->generateDescription($module, $entityName, $action, $changedFields);
            }

            $sql = "INSERT INTO activity_history (
                module, entity_id, entity_name, action,
                old_data, new_data, changed_fields,
                performed_by, performed_by_name, user_role,
                ip_address, user_agent, session_id,
                request_method, request_url, description,
                status, error_message
            ) VALUES (
                :module, :entity_id, :entity_name, :action,
                :old_data, :new_data, :changed_fields,
                :performed_by, :performed_by_name, :user_role,
                :ip_address, :user_agent, :session_id,
                :request_method, :request_url, :description,
                :status, :error_message
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':module' => $module,
                ':entity_id' => $entityId,
                ':entity_name' => $entityName,
                ':action' => $action,
                ':old_data' => $oldDataJson,
                ':new_data' => $newDataJson,
                ':changed_fields' => $changedFieldsJson,
                ':performed_by' => $this->currentUser,
                ':performed_by_name' => $this->userName,
                ':user_role' => $this->userRole,
                ':ip_address' => $this->getClientIP(),
                ':user_agent' => $this->getUserAgent(),
                ':session_id' => $this->getSessionId(),
                ':request_method' => $this->getRequestMethod(),
                ':request_url' => $this->getRequestUrl(),
                ':description' => $description,
                ':status' => $status,
                ':error_message' => $errorMessage
            ]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log with transaction support
     * If logging fails, the entire operation rolls back
     *
     * @param string $module Module name
     * @param string $entityId Entity ID
     * @param string $action Action type
     * @param mixed $oldData Old data
     * @param mixed $newData New data
     * @param callable $operation The database operation to perform
     * @param string|null $description Custom description
     * @param string|null $entityName Entity name
     * @return array ['success' => bool, 'data' => mixed, 'history_id' => int|null, 'error' => string|null]
     */
    public function logWithTransaction(
        $module,
        $entityId,
        $action,
        $oldData,
        $newData,
        callable $operation,
        $description = null,
        $entityName = null
    ) {
        $result = [
            'success' => false,
            'data' => null,
            'history_id' => null,
            'error' => null
        ];

        try {
            $this->pdo->beginTransaction();

            // Execute the main operation
            $operationResult = $operation($this->pdo);

            if ($operationResult === false) {
                throw new Exception("Operation returned false, rolling back");
            }

            // Log the activity
            $historyId = $this->log(
                $module,
                $entityId,
                $action,
                $oldData,
                $newData,
                $description,
                $entityName,
                'SUCCESS'
            );

            if ($historyId === false) {
                throw new Exception("Failed to log activity history");
            }

            $this->pdo->commit();

            $result['success'] = true;
            $result['data'] = $operationResult;
            $result['history_id'] = $historyId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $result['error'] = $e->getMessage();

            // Log the failed attempt
            $this->log(
                $module,
                $entityId,
                $action,
                $oldData,
                $newData,
                $description,
                $entityName,
                'FAILED',
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Shortcut for logging ADD action
     */
    public function logAdd($module, $entityId, $newData, $entityName = null, $description = null)
    {
        return $this->log($module, $entityId, 'ADD', null, $newData, $description, $entityName);
    }

    /**
     * Shortcut for logging UPDATE action
     */
    public function logUpdate($module, $entityId, $oldData, $newData, $entityName = null, $description = null)
    {
        return $this->log($module, $entityId, 'UPDATE', $oldData, $newData, $description, $entityName);
    }

    /**
     * Shortcut for logging DELETE action
     */
    public function logDelete($module, $entityId, $oldData, $entityName = null, $description = null)
    {
        return $this->log($module, $entityId, 'DELETE', $oldData, null, $description, $entityName);
    }

    /**
     * Shortcut for logging RESTORE action
     */
    public function logRestore($module, $entityId, $restoredData, $entityName = null, $description = null)
    {
        return $this->log($module, $entityId, 'RESTORE', null, $restoredData, $description, $entityName);
    }

    /**
     * Log user login
     */
    public function logLogin($userId, $userName, $userRole)
    {
        $this->setUserContext($userId, $userName, $userRole);
        return $this->log('auth', $userId, 'LOGIN', null, [
            'user_id' => $userId,
            'user_name' => $userName,
            'user_role' => $userRole,
            'login_time' => date('Y-m-d H:i:s')
        ], "User '{$userName}' logged in", $userName);
    }

    /**
     * Log user logout
     */
    public function logLogout($userId = null, $userName = null)
    {
        $userId = $userId ?? $this->currentUser;
        $userName = $userName ?? $this->userName;
        return $this->log('auth', $userId, 'LOGOUT', null, [
            'logout_time' => date('Y-m-d H:i:s')
        ], "User '{$userName}' logged out", $userName);
    }

    /**
     * Get history for a specific entity
     */
    public function getEntityHistory($module, $entityId, $limit = 50)
    {
        $sql = "SELECT * FROM activity_history
                WHERE module = :module AND entity_id = :entity_id AND status = 'SUCCESS'
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':module', $module, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent activity across all modules
     */
    public function getRecentActivity($limit = 50, $module = null)
    {
        $sql = "SELECT * FROM activity_history WHERE status = 'SUCCESS'";
        $params = [];

        if ($module) {
            $sql .= " AND module = :module";
            $params[':module'] = $module;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity statistics for a module
     */
    public function getModuleStats($module, $days = 30)
    {
        $sql = "SELECT
                    action,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM activity_history
                WHERE module = :module
                  AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  AND status = 'SUCCESS'
                GROUP BY action, DATE(created_at)
                ORDER BY date DESC, action";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':module', $module, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user activity summary
     */
    public function getUserActivity($userId, $days = 30)
    {
        $sql = "SELECT
                    module,
                    action,
                    COUNT(*) as count
                FROM activity_history
                WHERE performed_by = :user_id
                  AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                  AND status = 'SUCCESS'
                GROUP BY module, action
                ORDER BY count DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search activity history
     */
    public function searchHistory($searchTerm, $filters = [], $limit = 50, $offset = 0)
    {
        $conditions = ["status = 'SUCCESS'"];
        $params = [];

        if (!empty($searchTerm)) {
            $conditions[] = "(description LIKE :search OR entity_name LIKE :search2 OR entity_id LIKE :search3)";
            $params[':search'] = "%{$searchTerm}%";
            $params[':search2'] = "%{$searchTerm}%";
            $params[':search3'] = "%{$searchTerm}%";
        }

        if (!empty($filters['module'])) {
            $conditions[] = "module = :module";
            $params[':module'] = $filters['module'];
        }

        if (!empty($filters['action'])) {
            $conditions[] = "action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = "performed_by = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT * FROM activity_history WHERE {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * =====================================================
 * GLOBAL HELPER FUNCTION
 * For quick access without instantiating the class
 * =====================================================
 */

if (!function_exists('logActivityHistory')) {
    function logActivityHistory($pdo, $module, $entityId, $action, $oldData = null, $newData = null, $description = null, $entityName = null)
    {
        static $logger = null;
        if ($logger === null) {
            $logger = new ActivityLogger($pdo);
        }
        return $logger->log($module, $entityId, $action, $oldData, $newData, $description, $entityName);
    }
}
