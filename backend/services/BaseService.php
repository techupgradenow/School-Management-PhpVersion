<?php
/**
 * Base Service Class
 *
 * Provides common business logic patterns for all module services.
 * Services contain business rules and orchestrate repositories.
 *
 * Responsibilities:
 * - Business logic and validation
 * - Transaction management
 * - Activity logging
 * - Error handling
 */

require_once __DIR__ . '/../helpers/ActivityLogger.php';
require_once __DIR__ . '/../helpers/PermissionManager.php';

abstract class BaseService {
    protected PDO $db;
    protected ActivityLogger $logger;
    protected PermissionManager $permissions;
    protected string $module = ''; // Override in child classes

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->logger = new ActivityLogger($db);
        $this->permissions = new PermissionManager($db);
    }

    /**
     * Standard response format
     */
    protected function success($data = null, string $message = 'Success'): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Standard error format
     */
    protected function error(string $message, array $errors = [], int $code = 400): array {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code
        ];
    }

    /**
     * Validate required fields
     */
    protected function validateRequired(array $data, array $requiredFields): array {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Validate email format
     */
    protected function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number format (Indian format)
     */
    protected function validatePhone(string $phone): bool {
        return preg_match('/^[+]?[0-9]{10,13}$/', preg_replace('/[\s\-]/', '', $phone));
    }

    /**
     * Sanitize string input
     */
    protected function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction(): void {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    /**
     * Commit transaction
     */
    protected function commit(): void {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    /**
     * Rollback transaction
     */
    protected function rollback(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Log activity
     */
    protected function logActivity(string $action, $entityId, $oldData = null, $newData = null): void {
        $this->logger->log($this->module, $action, $entityId, $oldData, $newData);
    }

    /**
     * Check permission
     */
    protected function checkPermission(string $action): bool {
        return $this->permissions->can($this->module, $action);
    }

    /**
     * Require permission (throws exception if not allowed)
     */
    protected function requirePermission(string $action): void {
        if (!$this->checkPermission($action)) {
            throw new Exception("Permission denied: {$this->module}.{$action}", 403);
        }
    }

    /**
     * Format date for database
     */
    protected function formatDate(?string $date): ?string {
        if (!$date) return null;

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Format datetime for database
     */
    protected function formatDateTime(?string $datetime): ?string {
        if (!$datetime) return null;

        $timestamp = strtotime($datetime);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Generate unique code with prefix
     */
    protected function generateCode(string $prefix, int $length = 6): string {
        $number = str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    /**
     * Paginate results
     */
    protected function paginate(array $items, int $total, int $page, int $perPage): array {
        return [
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
                'hasMore' => ($page * $perPage) < $total
            ]
        ];
    }
}
