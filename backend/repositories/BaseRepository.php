<?php
/**
 * Base Repository Class
 *
 * Provides standardized data access with automatic multi-tenant isolation.
 * All module repositories should extend this class.
 *
 * Key Features:
 * - Automatic school_id filtering for multi-tenant support
 * - UUID generation for primary keys
 * - Standard CRUD operations
 * - Audit field population (created_at, updated_at, created_by, updated_by)
 */

require_once __DIR__ . '/../helpers/TenantContext.php';

abstract class BaseRepository {
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected ?string $schoolId = null;
    protected bool $isMultiTenant = true; // Set to false for global tables

    public function __construct(PDO $db) {
        $this->db = $db;

        // Get school context for multi-tenant filtering
        if ($this->isMultiTenant) {
            $tenantContext = TenantContext::getInstance();
            $this->schoolId = $tenantContext->getSchoolId();
        }
    }

    /**
     * Get database connection (for transactions in services)
     */
    public function getDb(): PDO {
        return $this->db;
    }

    /**
     * Find record by primary key
     */
    public function findById($id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = [':id' => $id];

        if ($this->isMultiTenant && $this->schoolId) {
            $sql .= " AND school_id = :school_id";
            $params[':school_id'] = $this->schoolId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find all records with optional filters and pagination
     */
    public function findAll(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = ''): array {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        // Add school_id filter for multi-tenant
        if ($this->isMultiTenant && $this->schoolId) {
            $sql .= " AND school_id = :school_id";
            $params[':school_id'] = $this->schoolId;
        }

        // Add dynamic filters
        foreach ($filters as $field => $value) {
            if ($value === null) continue;

            if (is_array($value)) {
                // Handle IN clause
                $placeholders = [];
                foreach ($value as $i => $v) {
                    $key = ":{$field}_{$i}";
                    $placeholders[] = $key;
                    $params[$key] = $v;
                }
                $sql .= " AND {$field} IN (" . implode(', ', $placeholders) . ")";
            } else {
                $sql .= " AND {$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }

        // Add ordering
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        // Add pagination
        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$offset}, {$perPage}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count records with optional filters
     */
    public function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($this->isMultiTenant && $this->schoolId) {
            $sql .= " AND school_id = :school_id";
            $params[':school_id'] = $this->schoolId;
        }

        foreach ($filters as $field => $value) {
            if ($value === null) continue;
            $sql .= " AND {$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Create new record
     */
    public function create(array $data): string {
        // Add school_id for multi-tenant
        if ($this->isMultiTenant && $this->schoolId) {
            $data['school_id'] = $this->schoolId;
        }

        // Generate UUID if not provided
        if (!isset($data[$this->primaryKey])) {
            $data[$this->primaryKey] = $this->generateUuid();
        }

        // Add audit fields
        $data['created_at'] = date('Y-m-d H:i:s');
        if (!isset($data['created_by'])) {
            $data['created_by'] = $this->getCurrentUser();
        }

        $columns = implode(', ', array_map(fn($col) => "`{$col}`", array_keys($data)));
        $placeholders = implode(', ', array_map(fn($col) => ":{$col}", array_keys($data)));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $data[$this->primaryKey];
    }

    /**
     * Update existing record
     */
    public function update($id, array $data): bool {
        // Add audit fields
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (!isset($data['updated_by'])) {
            $data['updated_by'] = $this->getCurrentUser();
        }

        // Remove primary key from update data
        unset($data[$this->primaryKey]);

        $sets = [];
        foreach (array_keys($data) as $field) {
            $sets[] = "`{$field}` = :{$field}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) .
               " WHERE {$this->primaryKey} = :_id";

        if ($this->isMultiTenant && $this->schoolId) {
            $sql .= " AND school_id = :school_id";
            $data['school_id'] = $this->schoolId;
        }

        $data['_id'] = $id;

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Delete record (soft delete if is_deleted column exists, otherwise hard delete)
     */
    public function delete($id): bool {
        // Check if soft delete is supported
        $columns = $this->getTableColumns();

        if (in_array('is_deleted', $columns)) {
            // Soft delete
            return $this->update($id, ['is_deleted' => 1]);
        }

        // Hard delete
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = [':id' => $id];

        if ($this->isMultiTenant && $this->schoolId) {
            $sql .= " AND school_id = :school_id";
            $params[':school_id'] = $this->schoolId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Find records by specific field
     */
    public function findBy(string $field, $value): array {
        return $this->findAll([$field => $value]);
    }

    /**
     * Find single record by specific field
     */
    public function findOneBy(string $field, $value): ?array {
        $results = $this->findAll([$field => $value], 1, 1);
        return $results[0] ?? null;
    }

    /**
     * Check if record exists
     */
    public function exists($id): bool {
        return $this->findById($id) !== null;
    }

    /**
     * Execute raw query with automatic school_id injection
     */
    protected function query(string $sql, array $params = []): array {
        if ($this->isMultiTenant && $this->schoolId && strpos($sql, ':school_id') !== false) {
            $params[':school_id'] = $this->schoolId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute raw statement
     */
    protected function execute(string $sql, array $params = []): bool {
        if ($this->isMultiTenant && $this->schoolId && strpos($sql, ':school_id') !== false) {
            $params[':school_id'] = $this->schoolId;
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Generate UUID v4
     */
    protected function generateUuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get current user from session
     */
    protected function getCurrentUser(): ?string {
        return $_SESSION['user_id'] ?? $_SESSION['username'] ?? null;
    }

    /**
     * Get table column names
     */
    protected function getTableColumns(): array {
        $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->db->rollBack();
    }
}
