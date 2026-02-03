<?php
/**
 * Tenant Context
 * Manages the current school context for multi-tenant operations
 *
 * Usage:
 *   $tenant = TenantContext::getInstance($pdo);
 *   $tenant->setSchool($schoolId);
 *   $schoolId = $tenant->getSchoolId();
 *   $config = $tenant->getFullContext();
 */

require_once __DIR__ . '/FeatureManager.php';
require_once __DIR__ . '/PermissionManager.php';

class TenantContext
{
    private static ?TenantContext $instance = null;
    private PDO $pdo;
    private ?string $schoolId = null;
    private ?string $userId = null;
    private ?array $school = null;
    private ?FeatureManager $featureManager = null;
    private ?PermissionManager $permissionManager = null;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(PDO $pdo): TenantContext
    {
        if (self::$instance === null) {
            self::$instance = new TenantContext($pdo);
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Set the current school context
     */
    public function setSchool(string $schoolId): self
    {
        $this->schoolId = $schoolId;
        $this->school = null;
        $this->featureManager = null;
        $this->permissionManager = null;
        $this->loadSchool();
        return $this;
    }

    /**
     * Set the current user context
     */
    public function setUser(string $userId): self
    {
        $this->userId = $userId;
        $this->permissionManager = null;
        return $this;
    }

    /**
     * Initialize context from session/token
     */
    public function initFromSession(string $sessionToken): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT user_id, school_id
            FROM user_sessions
            WHERE token_hash = ? AND expires_at > NOW()
        ");
        $stmt->execute([hash('sha256', $sessionToken)]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return false;
        }

        $this->userId = $session['user_id'];
        if ($session['school_id']) {
            $this->setSchool($session['school_id']);
        }

        return true;
    }

    /**
     * Load school data
     */
    private function loadSchool(): void
    {
        if (!$this->schoolId) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT s.*, p.code as plan_code, p.name as plan_name
            FROM schools s
            LEFT JOIN plans p ON s.plan_id = p.id
            WHERE s.id = ? AND s.is_active = 1 AND s.deleted_at IS NULL
        ");
        $stmt->execute([$this->schoolId]);
        $this->school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->school) {
            throw new Exception("School not found or inactive");
        }
    }

    /**
     * Get current school ID
     */
    public function getSchoolId(): ?string
    {
        return $this->schoolId;
    }

    /**
     * Get current user ID
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Get school data
     */
    public function getSchool(): ?array
    {
        return $this->school;
    }

    /**
     * Get Feature Manager instance
     */
    public function features(): FeatureManager
    {
        if ($this->featureManager === null) {
            if (!$this->schoolId) {
                throw new Exception("School context not set");
            }
            $this->featureManager = new FeatureManager($this->pdo, $this->schoolId);
        }
        return $this->featureManager;
    }

    /**
     * Get Permission Manager instance
     */
    public function permissions(): PermissionManager
    {
        if ($this->permissionManager === null) {
            if (!$this->userId || !$this->schoolId) {
                throw new Exception("User and school context not set");
            }
            $this->permissionManager = new PermissionManager($this->pdo, $this->userId, $this->schoolId);
        }
        return $this->permissionManager;
    }

    /**
     * Check if user has feature access
     */
    public function hasFeature(string $featureCode): bool
    {
        return $this->features()->hasFeature($featureCode);
    }

    /**
     * Check if user has permission
     */
    public function can(string $permissionCode): bool
    {
        return $this->permissions()->can($permissionCode);
    }

    /**
     * Require both feature and permission
     */
    public function requireAccess(string $featureCode, string $permissionCode): void
    {
        if (!$this->hasFeature($featureCode)) {
            throw new Exception("Feature not available in your plan: {$featureCode}");
        }

        if (!$this->can($permissionCode)) {
            throw new Exception("Permission denied: {$permissionCode}");
        }
    }

    /**
     * Get full context for API response / frontend
     */
    public function getFullContext(): array
    {
        $context = [
            'school' => null,
            'user' => null,
            'features' => [],
            'permissions' => [],
            'subscription' => null
        ];

        if ($this->school) {
            $context['school'] = [
                'id' => $this->school['id'],
                'code' => $this->school['code'],
                'name' => $this->school['name'],
                'logo' => $this->school['logo'],
                'timezone' => $this->school['timezone'],
                'currency' => $this->school['currency']
            ];
            $context['subscription'] = $this->features()->getSubscriptionStatus();
            $context['features'] = $this->features()->getAccessibleFeatures();
        }

        if ($this->userId && $this->schoolId) {
            $context['user'] = $this->permissions()->getUserContext();
            $context['permissions'] = $this->permissions()->getAllPermissions();
        }

        return $context;
    }

    /**
     * Get navigation menu based on features and permissions
     */
    public function getNavigationMenu(): array
    {
        $features = $this->features()->getAccessibleFeatures();
        $menu = [];

        // Group by category
        $categories = [
            'core' => ['label' => 'Core', 'icon' => 'fa-home', 'items' => []],
            'academic' => ['label' => 'Academic', 'icon' => 'fa-graduation-cap', 'items' => []],
            'finance' => ['label' => 'Finance', 'icon' => 'fa-dollar-sign', 'items' => []],
            'resources' => ['label' => 'Resources', 'icon' => 'fa-cubes', 'items' => []],
            'communication' => ['label' => 'Communication', 'icon' => 'fa-comments', 'items' => []],
            'analytics' => ['label' => 'Reports', 'icon' => 'fa-chart-bar', 'items' => []],
            'integration' => ['label' => 'Integrations', 'icon' => 'fa-plug', 'items' => []],
            'enterprise' => ['label' => 'Enterprise', 'icon' => 'fa-building', 'items' => []]
        ];

        foreach ($features as $feature) {
            $category = $feature['category'] ?? 'core';
            if (!isset($categories[$category])) {
                $category = 'core';
            }

            // Check view permission for the feature
            $viewPermission = $feature['code'] . '.view';
            $hasPermission = true;

            try {
                $hasPermission = $this->can($viewPermission);
            } catch (Exception $e) {
                // Permission doesn't exist, allow access
            }

            if ($hasPermission) {
                $categories[$category]['items'][] = [
                    'code' => $feature['code'],
                    'name' => $feature['name'],
                    'icon' => $feature['icon'],
                    'route' => $feature['route']
                ];
            }
        }

        // Only return categories with items
        foreach ($categories as $key => $category) {
            if (!empty($category['items'])) {
                $menu[] = $category;
            }
        }

        return $menu;
    }

    /**
     * Get schools user has access to
     */
    public function getUserSchools(): array
    {
        if (!$this->userId) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT s.id, s.code, s.name, s.logo, su.user_type, su.is_primary_school,
                   r.code as role_code, r.name as role_name
            FROM school_users su
            INNER JOIN schools s ON su.school_id = s.id
            INNER JOIN roles r ON su.role_id = r.id
            WHERE su.user_id = ? AND su.is_active = 1 AND s.is_active = 1
            ORDER BY su.is_primary_school DESC, s.name ASC
        ");
        $stmt->execute([$this->userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Switch to a different school
     */
    public function switchSchool(string $schoolId): bool
    {
        if (!$this->userId) {
            return false;
        }

        // Verify user has access to this school
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM school_users
            WHERE user_id = ? AND school_id = ? AND is_active = 1
        ");
        $stmt->execute([$this->userId, $schoolId]);

        if (!$stmt->fetchColumn()) {
            return false;
        }

        $this->setSchool($schoolId);
        return true;
    }

    /**
     * Create audit log entry
     */
    public function audit(string $action, string $entityType, ?string $entityId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO audit_log (school_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->schoolId,
            $this->userId,
            $action,
            $entityType,
            $entityId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Log feature usage
     */
    public function logFeatureUsage(string $featureCode, string $action, ?array $metadata = null): void
    {
        // Get feature ID
        $stmt = $this->pdo->prepare("SELECT id FROM features WHERE code = ?");
        $stmt->execute([$featureCode]);
        $featureId = $stmt->fetchColumn();

        if (!$featureId) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO feature_usage_log (school_id, feature_id, user_id, action, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->schoolId,
            $featureId,
            $this->userId,
            $action,
            $metadata ? json_encode($metadata) : null
        ]);
    }
}
