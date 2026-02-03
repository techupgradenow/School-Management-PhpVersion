<?php
/**
 * Permission Manager
 * Handles role-based access control (RBAC) for multi-tenant system
 *
 * Usage:
 *   $pm = new PermissionManager($pdo, $userId, $schoolId);
 *   if ($pm->can('students.view')) { ... }
 *   if ($pm->canAny(['students.view', 'students.edit'])) { ... }
 *   $permissions = $pm->getAllPermissions();
 */

class PermissionManager
{
    private PDO $pdo;
    private string $userId;
    private string $schoolId;
    private ?array $user = null;
    private ?array $schoolUser = null;
    private ?array $role = null;
    private array $permissions = [];
    private array $permissionOverrides = [];

    public function __construct(PDO $pdo, string $userId, string $schoolId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->schoolId = $schoolId;
        $this->loadUserData();
    }

    /**
     * Load user, role, and permissions data
     */
    private function loadUserData(): void
    {
        // Check if super admin
        $stmt = $this->pdo->prepare("
            SELECT * FROM global_users WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$this->userId]);
        $this->user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->user) {
            throw new Exception("User not found or inactive");
        }

        // Super admins have all permissions
        if ($this->user['is_super_admin']) {
            $this->loadAllPermissions();
            return;
        }

        // Get school user with role
        $stmt = $this->pdo->prepare("
            SELECT su.*, r.code as role_code, r.name as role_name, r.hierarchy_level
            FROM school_users su
            INNER JOIN roles r ON su.role_id = r.id
            WHERE su.user_id = ? AND su.school_id = ? AND su.is_active = 1
        ");
        $stmt->execute([$this->userId, $this->schoolId]);
        $this->schoolUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->schoolUser) {
            throw new Exception("User is not a member of this school");
        }

        $this->role = [
            'id' => $this->schoolUser['role_id'],
            'code' => $this->schoolUser['role_code'],
            'name' => $this->schoolUser['role_name'],
            'hierarchy_level' => $this->schoolUser['hierarchy_level']
        ];

        // Load role permissions
        $this->loadRolePermissions();

        // Apply user-specific overrides
        $this->applyPermissionOverrides();
    }

    /**
     * Load all permissions (for super admin)
     */
    private function loadAllPermissions(): void
    {
        $stmt = $this->pdo->query("SELECT code FROM permissions");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $this->permissions[$code] = true;
        }
    }

    /**
     * Load permissions for user's role
     */
    private function loadRolePermissions(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT p.code, rp.is_granted, rp.conditions
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$this->schoolUser['role_id']]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->permissions[$row['code']] = [
                'granted' => (bool) $row['is_granted'],
                'conditions' => $row['conditions'] ? json_decode($row['conditions'], true) : null
            ];
        }
    }

    /**
     * Apply user-specific permission overrides
     */
    private function applyPermissionOverrides(): void
    {
        if (empty($this->schoolUser['permissions_override'])) {
            return;
        }

        $overrides = json_decode($this->schoolUser['permissions_override'], true);
        if (!is_array($overrides)) {
            return;
        }

        foreach ($overrides as $code => $granted) {
            $this->permissionOverrides[$code] = (bool) $granted;
        }
    }

    /**
     * Check if user has a specific permission
     */
    public function can(string $permissionCode, ?array $context = null): bool
    {
        // Check user-specific override first
        if (isset($this->permissionOverrides[$permissionCode])) {
            return $this->permissionOverrides[$permissionCode];
        }

        // Check role permission
        if (!isset($this->permissions[$permissionCode])) {
            return false;
        }

        $permission = $this->permissions[$permissionCode];

        // Simple boolean (super admin)
        if (is_bool($permission)) {
            return $permission;
        }

        // Check if granted
        if (!$permission['granted']) {
            return false;
        }

        // Check conditions if context provided
        if ($permission['conditions'] && $context) {
            return $this->evaluateConditions($permission['conditions'], $context);
        }

        return true;
    }

    /**
     * Check if user has any of the specified permissions
     */
    public function canAny(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $code) {
            if ($this->can($code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the specified permissions
     */
    public function canAll(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $code) {
            if (!$this->can($code)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate conditional permissions
     * Conditions can include: own_data, same_class, department, etc.
     */
    private function evaluateConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition => $value) {
            switch ($condition) {
                case 'own_data':
                    // User can only access their own data
                    if ($value && isset($context['owner_id'])) {
                        if ($context['owner_id'] !== $this->userId) {
                            return false;
                        }
                    }
                    break;

                case 'same_class':
                    // User can only access data from their class
                    if ($value && isset($context['class_id'], $this->schoolUser['department'])) {
                        // Would need to verify class assignment
                    }
                    break;

                case 'department':
                    // User can only access data from their department
                    if ($value && isset($context['department'])) {
                        if ($context['department'] !== $this->schoolUser['department']) {
                            return false;
                        }
                    }
                    break;

                case 'max_hierarchy':
                    // User can only manage users below their hierarchy level
                    if (isset($context['target_hierarchy'])) {
                        if ($context['target_hierarchy'] <= $this->role['hierarchy_level']) {
                            return false;
                        }
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Get all granted permissions for the user
     */
    public function getAllPermissions(): array
    {
        $granted = [];

        foreach ($this->permissions as $code => $permission) {
            if (is_bool($permission)) {
                if ($permission) {
                    $granted[] = $code;
                }
            } elseif ($permission['granted']) {
                $granted[] = $code;
            }
        }

        // Apply overrides
        foreach ($this->permissionOverrides as $code => $isGranted) {
            if ($isGranted && !in_array($code, $granted)) {
                $granted[] = $code;
            } elseif (!$isGranted) {
                $granted = array_diff($granted, [$code]);
            }
        }

        return array_values($granted);
    }

    /**
     * Get user's role information
     */
    public function getRole(): ?array
    {
        return $this->role;
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return (bool) ($this->user['is_super_admin'] ?? false);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleCode): bool
    {
        if ($this->isSuperAdmin() && $roleCode === 'super_admin') {
            return true;
        }
        return $this->role && $this->role['code'] === $roleCode;
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(array $roleCodes): bool
    {
        if ($this->isSuperAdmin() && in_array('super_admin', $roleCodes)) {
            return true;
        }
        return $this->role && in_array($this->role['code'], $roleCodes);
    }

    /**
     * Check if user can manage another user (based on hierarchy)
     */
    public function canManageUser(string $targetUserId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Get target user's hierarchy
        $stmt = $this->pdo->prepare("
            SELECT r.hierarchy_level
            FROM school_users su
            INNER JOIN roles r ON su.role_id = r.id
            WHERE su.user_id = ? AND su.school_id = ?
        ");
        $stmt->execute([$targetUserId, $this->schoolId]);
        $targetHierarchy = $stmt->fetchColumn();

        if ($targetHierarchy === false) {
            return false;
        }

        // Can only manage users with higher hierarchy level (lower power)
        return $this->role['hierarchy_level'] < $targetHierarchy;
    }

    /**
     * Get permissions grouped by resource
     */
    public function getPermissionsByResource(): array
    {
        $stmt = $this->pdo->query("
            SELECT code, resource, action, name FROM permissions ORDER BY resource, action
        ");

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $permission) {
            $resource = $permission['resource'];
            if (!isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }

            $grouped[$resource][] = [
                'code' => $permission['code'],
                'action' => $permission['action'],
                'name' => $permission['name'],
                'granted' => $this->can($permission['code'])
            ];
        }

        return $grouped;
    }

    /**
     * Require a permission (throw exception if not granted)
     */
    public function require(string $permissionCode, string $message = null): void
    {
        if (!$this->can($permissionCode)) {
            throw new Exception($message ?? "Permission denied: {$permissionCode}");
        }
    }

    /**
     * Get user info for API response
     */
    public function getUserContext(): array
    {
        return [
            'user_id' => $this->userId,
            'school_id' => $this->schoolId,
            'is_super_admin' => $this->isSuperAdmin(),
            'role' => $this->role,
            'user_type' => $this->schoolUser['user_type'] ?? null,
            'permissions' => $this->getAllPermissions()
        ];
    }
}
