<?php
/**
 * Optimized Session Manager
 * EduManage Pro - School Management System
 *
 * Custom session handler for 500+ concurrent users
 * Uses database storage for session persistence and scalability
 */

class SessionManager {
    private static $pdo = null;
    private static $initialized = false;

    /**
     * Initialize custom session handler
     */
    public static function init($pdo) {
        if (self::$initialized) {
            return;
        }

        self::$pdo = $pdo;

        // Create sessions table if not exists
        self::createSessionsTable();

        // Register custom session handlers
        session_set_save_handler(
            [__CLASS__, 'open'],
            [__CLASS__, 'close'],
            [__CLASS__, 'read'],
            [__CLASS__, 'write'],
            [__CLASS__, 'destroy'],
            [__CLASS__, 'gc']
        );

        // Configure session settings
        ini_set('session.gc_maxlifetime', 3600); // 1 hour
        ini_set('session.cookie_lifetime', 0); // Until browser closes
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');

        self::$initialized = true;
    }

    /**
     * Create sessions table
     */
    private static function createSessionsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                user_id VARCHAR(50),
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                data TEXT,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_last_activity (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        try {
            self::$pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Session table creation error: " . $e->getMessage());
        }
    }

    /**
     * Session open handler
     */
    public static function open($savePath, $sessionName) {
        return true;
    }

    /**
     * Session close handler
     */
    public static function close() {
        return true;
    }

    /**
     * Session read handler
     */
    public static function read($sessionId) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT data FROM sessions
                WHERE id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['data'] : '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Session write handler
     */
    public static function write($sessionId, $data) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmt = self::$pdo->prepare("
                INSERT INTO sessions (id, user_id, ip_address, user_agent, data)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    ip_address = VALUES(ip_address),
                    data = VALUES(data),
                    last_activity = CURRENT_TIMESTAMP
            ");

            return $stmt->execute([$sessionId, $userId, $ipAddress, $userAgent, $data]);
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Session destroy handler
     */
    public static function destroy($sessionId) {
        try {
            $stmt = self::$pdo->prepare("DELETE FROM sessions WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Session garbage collection handler
     */
    public static function gc($maxLifetime) {
        try {
            $stmt = self::$pdo->prepare("
                DELETE FROM sessions
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            return $stmt->execute([$maxLifetime]);
        } catch (PDOException $e) {
            error_log("Session GC error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active sessions count
     */
    public static function getActiveCount() {
        try {
            $stmt = self::$pdo->query("
                SELECT COUNT(*) FROM sessions
                WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get user's active sessions
     */
    public static function getUserSessions($userId) {
        try {
            $stmt = self::$pdo->prepare("
                SELECT id, ip_address, user_agent, last_activity, created_at
                FROM sessions
                WHERE user_id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Kill all sessions for a user
     */
    public static function killUserSessions($userId) {
        try {
            $stmt = self::$pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
