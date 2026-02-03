<?php
/**
 * Feature Manager
 * Handles feature access control based on school plan + overrides
 *
 * Usage:
 *   $fm = new FeatureManager($pdo, $schoolId);
 *   if ($fm->hasFeature('library')) { ... }
 *   $features = $fm->getAccessibleFeatures();
 */

class FeatureManager
{
    private PDO $pdo;
    private string $schoolId;
    private ?array $school = null;
    private ?array $plan = null;
    private ?array $features = null;
    private ?array $overrides = null;

    public function __construct(PDO $pdo, string $schoolId)
    {
        $this->pdo = $pdo;
        $this->schoolId = $schoolId;
        $this->loadSchoolData();
    }

    /**
     * Load school and plan data
     */
    private function loadSchoolData(): void
    {
        // Get school with plan
        $stmt = $this->pdo->prepare("
            SELECT s.*, p.code as plan_code, p.name as plan_name,
                   p.max_students, p.max_teachers, p.max_users, p.max_storage_gb
            FROM schools s
            LEFT JOIN plans p ON s.plan_id = p.id
            WHERE s.id = ? AND s.is_active = 1
        ");
        $stmt->execute([$this->schoolId]);
        $this->school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->school) {
            throw new Exception("School not found or inactive");
        }

        // Get plan features
        if ($this->school['plan_id']) {
            $stmt = $this->pdo->prepare("
                SELECT f.*, pf.is_enabled, pf.usage_limit, pf.limit_type, pf.custom_config
                FROM features f
                INNER JOIN plan_features pf ON f.id = pf.feature_id
                WHERE pf.plan_id = ? AND f.is_active = 1 AND pf.is_enabled = 1
            ");
            $stmt->execute([$this->school['plan_id']]);
            $this->features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // No plan = free features only
            $stmt = $this->pdo->query("
                SELECT * FROM features WHERE is_active = 1 AND is_premium = 0
            ");
            $this->features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get school-specific overrides
        $stmt = $this->pdo->prepare("
            SELECT sfo.*, f.code as feature_code
            FROM school_feature_overrides sfo
            INNER JOIN features f ON sfo.feature_id = f.id
            WHERE sfo.school_id = ?
            AND (sfo.valid_until IS NULL OR sfo.valid_until >= CURDATE())
        ");
        $stmt->execute([$this->schoolId]);
        $this->overrides = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $override) {
            $this->overrides[$override['feature_code']] = $override;
        }
    }

    /**
     * Check if school has access to a feature
     */
    public function hasFeature(string $featureCode): bool
    {
        // Check override first (can grant OR revoke access)
        if (isset($this->overrides[$featureCode])) {
            return (bool) $this->overrides[$featureCode]['is_enabled'];
        }

        // Check plan features
        foreach ($this->features as $feature) {
            if ($feature['code'] === $featureCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all accessible features for UI rendering
     */
    public function getAccessibleFeatures(): array
    {
        $accessible = [];
        $featureCodes = array_column($this->features, 'code');

        // Add plan features
        foreach ($this->features as $feature) {
            $accessible[$feature['code']] = [
                'code' => $feature['code'],
                'name' => $feature['name'],
                'icon' => $feature['icon'],
                'route' => $feature['route'],
                'category' => $feature['category'],
                'enabled' => true,
                'source' => 'plan'
            ];
        }

        // Apply overrides
        foreach ($this->overrides as $code => $override) {
            if ($override['is_enabled']) {
                // Grant access
                if (!isset($accessible[$code])) {
                    $accessible[$code] = [
                        'code' => $code,
                        'enabled' => true,
                        'source' => 'override'
                    ];
                }
            } else {
                // Revoke access
                unset($accessible[$code]);
            }
        }

        return array_values($accessible);
    }

    /**
     * Get feature usage limit
     */
    public function getFeatureLimit(string $featureCode): ?int
    {
        // Check override first
        if (isset($this->overrides[$featureCode]) && $this->overrides[$featureCode]['usage_limit'] !== null) {
            return $this->overrides[$featureCode]['usage_limit'];
        }

        // Check plan feature
        foreach ($this->features as $feature) {
            if ($feature['code'] === $featureCode) {
                return $feature['usage_limit'] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if school is within usage limits
     */
    public function checkLimit(string $resource): array
    {
        $limits = [
            'students' => $this->school['max_students'],
            'teachers' => $this->school['max_teachers'],
            'users' => $this->school['max_users'],
            'storage' => $this->school['max_storage_gb']
        ];

        if (!isset($limits[$resource])) {
            return ['allowed' => true, 'limit' => null, 'current' => null];
        }

        $limit = $limits[$resource];
        if ($limit === null) {
            return ['allowed' => true, 'limit' => 'unlimited', 'current' => null];
        }

        // Get current count
        $current = $this->getCurrentCount($resource);

        return [
            'allowed' => $current < $limit,
            'limit' => $limit,
            'current' => $current,
            'remaining' => max(0, $limit - $current)
        ];
    }

    /**
     * Get current resource count
     */
    private function getCurrentCount(string $resource): int
    {
        $tables = [
            'students' => 'students',
            'teachers' => 'teachers',
            'users' => 'school_users'
        ];

        if (!isset($tables[$resource])) {
            return 0;
        }

        // Note: In multi-tenant setup, we'd filter by school_id
        // For now, return total count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$tables[$resource]}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get school subscription status
     */
    public function getSubscriptionStatus(): array
    {
        return [
            'plan_code' => $this->school['plan_code'] ?? 'free',
            'plan_name' => $this->school['plan_name'] ?? 'Free',
            'status' => $this->school['subscription_status'],
            'is_premium' => (bool) $this->school['is_premium'],
            'starts_at' => $this->school['subscription_start'],
            'ends_at' => $this->school['subscription_end'],
            'trial_ends_at' => $this->school['trial_ends_at'],
            'days_remaining' => $this->getDaysRemaining()
        ];
    }

    /**
     * Calculate days remaining in subscription
     */
    private function getDaysRemaining(): ?int
    {
        $endDate = $this->school['subscription_end'] ?? $this->school['trial_ends_at'];
        if (!$endDate) {
            return null;
        }

        $end = new DateTime($endDate);
        $now = new DateTime();
        $diff = $now->diff($end);

        return $diff->invert ? 0 : $diff->days;
    }

    /**
     * Get UI config for frontend
     * Returns what features to show/hide
     */
    public function getUIConfig(): array
    {
        return [
            'school' => [
                'id' => $this->school['id'],
                'name' => $this->school['name'],
                'code' => $this->school['code'],
                'logo' => $this->school['logo']
            ],
            'subscription' => $this->getSubscriptionStatus(),
            'features' => $this->getAccessibleFeatures(),
            'limits' => [
                'students' => $this->checkLimit('students'),
                'teachers' => $this->checkLimit('teachers'),
                'users' => $this->checkLimit('users')
            ]
        ];
    }
}
