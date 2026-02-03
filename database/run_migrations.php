<?php
/**
 * Multi-Tenant SaaS Database Migration Runner
 *
 * This script runs all migrations and seeders in order to set up the
 * multi-tenant database architecture.
 *
 * Usage: php run_migrations.php [--seed] [--fresh]
 *
 * Options:
 *   --seed   Run seeders after migrations
 *   --fresh  Drop all existing mt_ tables before running migrations
 */

// Include centralized environment configuration (Remote Hostinger DB only)
require_once __DIR__ . '/../backend/config/env.php';

// Validate we're using remote database (blocks localhost)
validateRemoteDatabase();

// Configuration - Uses ONLY remote Hostinger database
$config = getDbCredentials();

// Parse command line arguments
$options = getopt('', ['seed', 'fresh', 'help']);

if (isset($options['help'])) {
    echo "Multi-Tenant SaaS Database Migration Runner\n\n";
    echo "Usage: php run_migrations.php [options]\n\n";
    echo "Options:\n";
    echo "  --seed   Run seeders after migrations\n";
    echo "  --fresh  Drop all existing mt_ tables before running migrations\n";
    echo "  --help   Show this help message\n\n";
    exit(0);
}

$runSeeders = isset($options['seed']);
$freshInstall = isset($options['fresh']);

// Migration files in order
$migrations = [
    'migrations/001_system_level_tables.sql',
    'migrations/002_tenant_rbac_tables.sql',
    'migrations/003_tenant_domain_tables.sql',
    'migrations/004_audit_activity_tables.sql'
];

// Seeder files in order
$seeders = [
    'seeders/001_seed_system_data.sql'
];

// Colors for console output
function colorize($text, $color) {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];

    // Check if running in Windows console without ANSI support
    if (PHP_OS_FAMILY === 'Windows' && !getenv('ANSICON')) {
        return $text;
    }

    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function printHeader($text) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo colorize($text, 'blue') . "\n";
    echo str_repeat('=', 60) . "\n\n";
}

function printSuccess($text) {
    echo colorize("✓ ", 'green') . $text . "\n";
}

function printError($text) {
    echo colorize("✗ ", 'red') . $text . "\n";
}

function printWarning($text) {
    echo colorize("⚠ ", 'yellow') . $text . "\n";
}

function printInfo($text) {
    echo colorize("→ ", 'blue') . $text . "\n";
}

// Connect to database
printHeader("Multi-Tenant SaaS Database Migration Runner");

try {
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    printSuccess("Connected to MySQL server");
} catch (PDOException $e) {
    printError("Failed to connect to MySQL: " . $e->getMessage());
    exit(1);
}

// Create database if not exists
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['database']}`");
    printSuccess("Using database: {$config['database']}");
} catch (PDOException $e) {
    printError("Failed to create/use database: " . $e->getMessage());
    exit(1);
}

// Fresh install - drop existing tables
if ($freshInstall) {
    printWarning("Fresh install requested - dropping existing multi-tenant tables...");

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Get all tables starting with mt_ or system tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $mtTables = array_filter($tables, function($table) {
        return strpos($table, 'mt_') === 0
            || in_array($table, [
                'tenants', 'subscription_plans', 'plan_modules', 'system_modules',
                'system_actions', 'system_roles', 'system_admins', 'system_settings',
                'system_audit_log', 'tenant_invitations'
            ]);
    });

    foreach ($mtTables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            printInfo("Dropped table: $table");
        } catch (PDOException $e) {
            printWarning("Could not drop table $table: " . $e->getMessage());
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    printSuccess("Existing tables dropped");
}

// Create migrations tracking table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `_migrations` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Get current batch number
$batch = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM `_migrations`")->fetchColumn();

// Run migrations
printHeader("Running Migrations (Batch $batch)");

$migrationsRun = 0;
$basePath = __DIR__;

foreach ($migrations as $migration) {
    $migrationPath = $basePath . '/' . $migration;
    $migrationName = basename($migration);

    // Check if already run
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `_migrations` WHERE migration = ?");
    $stmt->execute([$migrationName]);

    if ($stmt->fetchColumn() > 0 && !$freshInstall) {
        printInfo("Skipped (already run): $migrationName");
        continue;
    }

    if (!file_exists($migrationPath)) {
        printError("Migration file not found: $migrationPath");
        continue;
    }

    printInfo("Running: $migrationName");

    try {
        $sql = file_get_contents($migrationPath);

        // Split by semicolon, but not within strings
        $statements = preg_split('/;\s*$/m', $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }

            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "table already exists" errors in non-fresh mode
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
            }
        }

        // Record migration
        $stmt = $pdo->prepare("INSERT INTO `_migrations` (migration, batch) VALUES (?, ?) ON DUPLICATE KEY UPDATE batch = ?");
        $stmt->execute([$migrationName, $batch, $batch]);

        printSuccess("Completed: $migrationName");
        $migrationsRun++;

    } catch (PDOException $e) {
        printError("Failed: $migrationName");
        printError("Error: " . $e->getMessage());

        // Ask if should continue
        echo "\nContinue with remaining migrations? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'y') {
            printError("Migration aborted by user");
            exit(1);
        }
    }
}

if ($migrationsRun > 0) {
    printSuccess("$migrationsRun migration(s) completed successfully");
} else {
    printInfo("No new migrations to run");
}

// Run seeders
if ($runSeeders) {
    printHeader("Running Seeders");

    foreach ($seeders as $seeder) {
        $seederPath = $basePath . '/' . $seeder;
        $seederName = basename($seeder);

        if (!file_exists($seederPath)) {
            printError("Seeder file not found: $seederPath");
            continue;
        }

        printInfo("Running: $seederName");

        try {
            $sql = file_get_contents($seederPath);
            $statements = preg_split('/;\s*$/m', $sql);

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }

                $pdo->exec($statement);
            }

            printSuccess("Completed: $seederName");

        } catch (PDOException $e) {
            printError("Failed: $seederName");
            printError("Error: " . $e->getMessage());
        }
    }
}

// Summary
printHeader("Migration Summary");

$tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$config['database']}'")->fetchColumn();
printInfo("Total tables in database: $tableCount");

$mtTableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$config['database']}' AND (table_name LIKE 'mt_%' OR table_name IN ('tenants', 'subscription_plans', 'plan_modules', 'system_modules', 'system_actions', 'system_roles', 'system_admins', 'system_settings', 'system_audit_log', 'tenant_invitations'))")->fetchColumn();
printInfo("Multi-tenant tables: $mtTableCount");

if ($runSeeders) {
    $modulesCount = $pdo->query("SELECT COUNT(*) FROM system_modules")->fetchColumn();
    $plansCount = $pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    $rolesCount = $pdo->query("SELECT COUNT(*) FROM system_roles")->fetchColumn();

    printInfo("System modules seeded: $modulesCount");
    printInfo("Subscription plans seeded: $plansCount");
    printInfo("System roles seeded: $rolesCount");
}

echo "\n";
printSuccess("Database setup completed successfully!");
echo "\n";

if ($runSeeders) {
    printWarning("Default system admin created:");
    printInfo("Email: admin@edumanage.pro");
    printInfo("Password: password");
    printWarning("IMPORTANT: Change the default password immediately!");
}

echo "\n";
