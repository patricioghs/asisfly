<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class NavigationBuilder
{
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
            if ($companyId <= 0 || !$this->registry->tablesReady()) {
                return $fallback;
            }

            $this->tenantAbilities->ensureDefaultAbilities($companyId);

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

            if (!$items) {
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

            return $navigation ?: $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
