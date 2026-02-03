<?php
/**
 * Environment Configuration
 * EduManage Pro - School Management System
 *
 * IMPORTANT: This application uses ONLY the remote Hostinger MySQL database.
 * Local database connections are DISABLED.
 *
 * All users connect to the same centralized remote database.
 */

// Prevent direct access
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// ============================================================================
// REMOTE DATABASE CONFIGURATION (Hostinger MySQL)
// ============================================================================
// WARNING: Do NOT modify these settings unless migrating to a new server.
// Local database connections are intentionally disabled.
// ============================================================================

define('DB_HOST', '193.203.184.194');       // Hostinger Remote MySQL Server
define('DB_PORT', '3306');                   // MySQL Port
define('DB_NAME', 'u282002960_upgradeschool'); // Remote Database Name
define('DB_USER', 'u282002960_upgradeschool'); // Remote Database Username
define('DB_PASS', 'UpgradeSchool@33');        // Remote Database Password
define('DB_CHARSET', 'utf8mb4');              // Character Set

// ============================================================================
// LOCAL DATABASE - DISABLED
// ============================================================================
// The following local database settings are commented out and disabled.
// DO NOT uncomment or use these settings.
//
// define('DB_HOST', 'localhost');    // DISABLED - DO NOT USE
// define('DB_USER', 'root');         // DISABLED - DO NOT USE
// define('DB_PASS', '');             // DISABLED - DO NOT USE
// define('DB_NAME', 'edumanage_pro'); // DISABLED - DO NOT USE
// ============================================================================

// Connection timeout settings for remote database
define('DB_CONNECT_TIMEOUT', 30);  // Connection timeout in seconds
define('DB_READ_TIMEOUT', 60);     // Read timeout in seconds

// Application settings
define('APP_ENV', 'development');   // production | development
define('APP_DEBUG', true);          // Set to false in production
define('DEV_AUTO_LOGIN', true);     // Auto-login for development (disable in production)

// Timezone
define('APP_TIMEZONE', 'Asia/Kolkata');

// Error logging path
define('ERROR_LOG_PATH', __DIR__ . '/../../logs/error.log');

/**
 * Get database connection string (DSN)
 * @return string PDO DSN string for remote Hostinger database
 */
function getDbDsn() {
    return sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
}

/**
 * Get database credentials array
 * @return array Database connection credentials
 */
function getDbCredentials() {
    return [
        'host' => DB_HOST,
        'port' => DB_PORT,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => DB_CHARSET
    ];
}

/**
 * Validate that we're using remote database (security check)
 * @return bool True if using remote database
 * @throws Exception If local database is detected
 */
function validateRemoteDatabase() {
    if (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1') {
        throw new Exception('ERROR: Local database connections are disabled. This application must use the remote Hostinger database.');
    }
    return true;
}
?>
