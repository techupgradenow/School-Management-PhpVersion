<?php
/**
 * Database Configuration
 * EduManage Pro - School Management System
 *
 * IMPORTANT: This application connects ONLY to the remote Hostinger MySQL database.
 * Local database connections are completely DISABLED.
 */

// Include centralized environment configuration
require_once __DIR__ . '/env.php';

// Validate we're using remote database (blocks localhost connections)
validateRemoteDatabase();

/**
 * Database Connection Class - Singleton Pattern
 * Connects exclusively to remote Hostinger MySQL database
 */
class Database {
    private static $instance = null;
    private $connection;
    private $host = DB_HOST;
    private $port = DB_PORT;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $charset = DB_CHARSET;

    /**
     * Private constructor to prevent multiple instances
     * Establishes connection to remote Hostinger database only
     */
    private function __construct() {
        // Security check: Block any localhost connections
        if ($this->host === 'localhost' || $this->host === '127.0.0.1') {
            $this->logError("SECURITY: Attempted localhost connection blocked. Remote DB required.");
            throw new Exception("Local database connections are disabled. Remote Hostinger database is required.");
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT,
                // Performance optimizations
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ];

            $this->connection = new PDO($dsn, $this->user, $this->pass, $options);

            // Set MySQL session variables for performance and compatibility
            // Combined into single exec for better performance
            $this->connection->exec("
                SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO',
                SESSION wait_timeout = 28800,
                SESSION interactive_timeout = 28800,
                collation_connection = 'utf8mb4_unicode_ci',
                SESSION group_concat_max_len = 1000000
            ");

        } catch (PDOException $e) {
            $this->logError("Remote Database Connection Failed: " . $e->getMessage());
            $this->handleConnectionError($e);
        }
    }

    /**
     * Handle database connection errors with detailed feedback
     */
    private function handleConnectionError(PDOException $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // Common remote connection errors
        $errorInfo = match(true) {
            str_contains($errorMessage, 'Connection refused') =>
                "Connection refused by remote server. Check if MySQL is running on Hostinger and port 3306 is open.",
            str_contains($errorMessage, 'Access denied') =>
                "Access denied. Verify username and password for Hostinger database.",
            str_contains($errorMessage, 'Unknown database') =>
                "Database 'u282002960_upgradeschool' not found on remote server.",
            str_contains($errorMessage, 'Host') && str_contains($errorMessage, 'not allowed') =>
                "Your IP address is not allowed to connect. Add your IP to Hostinger's Remote MySQL whitelist.",
            str_contains($errorMessage, 'timed out') || str_contains($errorMessage, 'timeout') =>
                "Connection timed out. Check network connectivity to 193.203.184.194.",
            str_contains($errorMessage, 'No route to host') =>
                "Cannot reach remote server. Check your internet connection.",
            default => "Remote database connection failed: " . $errorMessage
        };

        throw new Exception($errorInfo);
    }

    /**
     * Log error to file
     */
    private function logError($message) {
        $logFile = defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : __DIR__ . '/../../logs/error.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] DB ERROR: {$message}" . PHP_EOL;
        error_log($logMessage, 3, $logFile);
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
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Check if connection is alive and reconnect if needed
     */
    public function ping() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            self::$instance = null;
            return false;
        }
    }

    /**
     * Get connection info (for debugging)
     */
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->dbname,
            'charset' => $this->charset,
            'type' => 'REMOTE (Hostinger)',
            'server_info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO),
            'server_version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION)
        ];
    }

    // Prevent cloning of instance
    private function __clone() {}

    // Prevent unserialization of instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to get database connection
 * @return PDO Database connection to remote Hostinger database
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Helper function to get a new PDO connection directly
 * Use this when you need a fresh connection without singleton
 * @return PDO New database connection
 */
function getNewDbConnection() {
    validateRemoteDatabase();

    $dsn = getDbDsn();
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : __DIR__ . '/../../logs/error.log');

// Set timezone
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Kolkata');

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
