<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class NavigationBuilder
{
    private string $source = 'fallback';
    private string $reason = 'not_built';

    public function __construct(
        private ?AbilityRegistry $registry = null,
        private ?TenantAbilityService $tenantAbilities = null,
        private ?AbilityPermissionResolver $permissions = null
    ) {
        $this->registry ??= new AbilityRegistry();
        $this->tenantAbilities ??= new TenantAbilityService($this->registry);
        $this->permissions ??= new AbilityPermissionResolver();
    }

    public function build(int $companyId, array $user): array
    {
        $fallback = AbilityRegistry::fallbackNavigation();

        try {
            if (!$this->dynamicNavigationEnabled()) {
                $this->source = 'fallback';
                $this->reason = 'disabled_by_env';
                return $fallback;
            }

            if ($companyId <= 0 || !$this->registry->tablesReady()) {
                $this->source = 'fallback';
                $this->reason = $companyId <= 0 ? 'missing_company' : 'ability_tables_not_ready';
                return $fallback;
            }

            $this->tenantAbilities->ensureDefaultAbilities($companyId);
            $tenantAbilityCount = $this->tenantAbilityCount($companyId);

            $statement = Database::connection()->prepare(
                "SELECT ni.section, ni.label, ni.route, ni.icon, ni.badge_key, ni.permission_key
                 FROM tenant_abilities ta
                 INNER JOIN abilities a ON a.id = ta.ability_id
                 INNER JOIN ability_navigation_items ni ON ni.ability_id = a.id
                 WHERE ta.company_id = :company_id
                   AND ta.status = 'active'
                   AND a.status = 'active'
                   AND ni.is_active = TRUE
                 ORDER BY FIELD(ni.section, 'Inicio', 'Asistente', 'Comunicacion', 'Comercial', 'Inteligencia', 'Conocimiento', 'Integraciones', 'Empresa', 'Administracion (Superadmin)'),
                          ni.sort_order,
                          a.sort_order,
                          ni.id"
            );
            $statement->execute(['company_id' => $companyId]);
            $items = $statement->fetchAll(PDO::FETCH_ASSOC);

            if (!$items && $tenantAbilityCount === 0) {
                $this->source = 'fallback';
                $this->reason = 'no_tenant_abilities_seeded';
                return $fallback;
            }

            $navigation = [];
            foreach ($items as $item) {
                if (!$this->permissions->allows($user, $item['permission_key'] ?? null)) {
                    continue;
                }

                $section = (string) $item['section'];
                $navigation[$section] ??= [];
                $navigation[$section][] = [
                    (string) $item['route'],
                    (string) $item['label'],
                    (string) $item['icon'],
                    $item['badge_key'] ?: null,
                ];
            }

            $this->source = 'dynamic';
            $this->reason = $navigation ? 'ok' : 'no_visible_items_for_permissions_or_active_abilities';

            return $navigation;
        } catch (\Throwable) {
            $this->source = 'fallback';
            $this->reason = 'exception';
            return $fallback;
        }
    }

    public function source(): string
    {
        return $this->source;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private function dynamicNavigationEnabled(): bool
    {
        $value = strtolower((string) \config('app.dynamic_navigation', 'true'));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function tenantAbilityCount(int $companyId): int
    {
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM tenant_abilities WHERE company_id = :company_id');
        $statement->execute(['company_id' => $companyId]);

        return (int) $statement->fetchColumn();
    }
}
